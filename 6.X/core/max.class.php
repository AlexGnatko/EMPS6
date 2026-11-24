<?php

/**
 * EMPS_MaxBot - Max Bot API client (https://dev.max.ru/docs-api) on plain cURL, no libraries.
 *
 * Modeled on EMPS_TelegramBot but not tied to any project: it is transport only
 * (API requests, parsing of incoming updates, keyboard helpers). The bot logic lives in the project.
 *
 * Usage:
 *     require_once $emps->core_module("max.class");
 *     $max = new EMPS_MaxBot();
 *     $max->set_key(MAX_BOT);
 *     $max->send_message(['user_id' => 123], "Hello!", [
 *         'keyboard' => $max->keyboard([[$max->btn_callback("Yes", "yes"), $max->btn_link("Website", "https://example.com/")]]),
 *     ]);
 *
 * Webhook:
 *     $max->subscribe("https://example.ru/max-webhook/KEY/", MAX_WEBHOOK_SECRET, ['message_created', 'message_callback', 'bot_started']);
 *     // in the handler:
 *     if (!$max->verify_webhook_secret(MAX_WEBHOOK_SECRET)) { http_response_code(403); exit; }
 *     $update = $max->read_update();
 *
 * Optional constants (like TG_API_URL for Telegram): MAX_API_URL, MAX_CA_FILE.
 *
 * Notes:
 *  - Main API domain: https://platform-api2.max.ru, its certificate is issued by the Russian CA (Mintsifry).
 *    The root certificate is NOT installed into the server's system trust store: keep it as a separate file
 *    (outside the web root or in a protected folder) and point to it explicitly via $ca_file or the MAX_CA_FILE constant.
 *    It is applied only to requests made by this object (CURLOPT_CAINFO replaces the set of trusted CAs
 *    for the duration of the request); other connections of the server do not use it.
 *    If no path is set, the regular system trust store is used.
 *    Disabling TLS verification ($verify_ssl = false) is not recommended: the bot token travels in a header.
 *  - The token is passed ONLY in the Authorization header.
 *  - Rate limit: at most 2 messages per second per dialog/chat.
 *  - Webhook and long polling cannot be used at the same time.
 *  - Some methods answer HTTP 200 with "success": false on error - always check is_ok().
 *  - Only GET requests and 429 responses (request rejected, not executed) are retried. POST/PUT/DELETE are NOT
 *    retried on a network error or 5xx, to avoid creating a duplicate message.
 */
class EMPS_MaxBot {
    private $key = "";
    private $url = "https://platform-api2.max.ru/";

    // Network settings
    public $connect_timeout = 10;   // seconds to establish the connection, 0 - no limit
    public $timeout = 30;           // seconds for the whole request, 0 - no limit
    public $force_ipv4 = false;     // true - do not use IPv6
    public $proxy = "";             // e.g. "socks5h://host:1080" or "http://host:3128"
    public $proxy_auth = "";        // "user:password" for the proxy, if needed
    public $verify_ssl = true;      // verify the server certificate (keep it true)
    public $ca_file = "";           // path to the root certificate file (Mintsifry) - used only for Max requests
    public $retries = 2;            // how many times to repeat a GET or a 429 response
    public $retry_delay = 1;        // pause between retries, seconds

    // State of the last request (for debugging and logs)
    public $last_http_code = 0;
    public $last_error = "";
    public $last_response = null;

    public $secret_header = "X-Max-Bot-Api-Secret";

    // API address, e.g. "https://max-proxy.example.com/" - for a reverse proxy
    public function set_api_url($url) {
        $this->url = rtrim($url, "/") . "/";
    }

    public function set_key($key) {
        $this->key = $key;
        if (defined("MAX_API_URL")) {
            $this->set_api_url(MAX_API_URL);
        }
        if (defined("MAX_CA_FILE") && !$this->ca_file) {
            $this->ca_file = MAX_CA_FILE;
        }
    }

    public function get_key() {
        return $this->key;
    }

    public function get_api_url($resource) {
        return $this->url . ltrim($resource, "/");
    }

    protected function default_http_headers($with_body) {
        $headers = [
            'Accept: application/json',
            'Authorization: ' . $this->key,
        ];
        if ($with_body) {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        }
        return $headers;
    }

    protected function curl_opts(&$ch) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl ? true : false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verify_ssl ? 2 : 0);

        if ($this->ca_file) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->ca_file);
            curl_setopt($ch, CURLOPT_CAPATH, "");
        }
        if ($this->connect_timeout > 0) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);
        }
        if ($this->timeout > 0) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        }
        if ($this->force_ipv4) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
            if ($this->proxy_auth) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy_auth);
            }
        }

        return true;
    }

    protected function build_query($query) {
        $parts = [];
        foreach ($query as $param => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? "true" : "false";
            }
            if (is_array($value)) {
                $value = implode(",", $value);
            }
            $parts[] = rawurlencode($param) . '=' . rawurlencode($value);
        }
        return implode('&', $parts);
    }

    /**
     * Perform an API request.
     *
     * @param string $method GET, POST, PUT, DELETE, PATCH
     * @param string $resource e.g. "messages"
     * @param array $query query string parameters
     * @param array|null $body request body (will be JSON-encoded)
     * @return array API response; on failure - ['success' => false, 'code' => ..., 'message' => ...]
     */
    public function request($method, $resource, $query = [], $body = null) {
        $method = strtoupper($method);

        if ($this->ca_file && $this->verify_ssl && !is_readable($this->ca_file)) {
            return $this->fail('ca_file', 'Max API: CA file is not readable: ' . $this->ca_file);
        }

        $url = $this->get_api_url($resource);
        $qs = $this->build_query($query);
        if ($qs !== "") {
            $url .= '?' . $qs;
        }

        $attempt = 0;
        while (true) {
            $ch = curl_init($url);
            $this->curl_opts($ch);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->default_http_headers($body !== null));
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body === [] ? new stdClass() : $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            $raw = curl_exec($ch);
            $curl_error = curl_error($ch);
            $http_code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            curl_close($ch);

            $this->last_http_code = $http_code;
            $this->last_error = $curl_error;

            $can_retry = $attempt < $this->retries
                && ($method == "GET" || $http_code == 429);
            if ($can_retry && ($raw === false || $http_code == 429 || $http_code >= 500)) {
                $attempt++;
                sleep($this->retry_delay);
                continue;
            }
            break;
        }

        if ($raw === false) {
            return $this->fail('network', 'Max API request failed: ' . $curl_error);
        }

        $response = json_decode($raw, true);
        $this->last_response = $response;

        if (!is_array($response)) {
            if ($http_code >= 200 && $http_code < 300) {
                // Empty/unparsable response to a successful request
                return ['success' => true];
            }
            return $this->fail('http.' . $http_code, 'Max API unexpected response, HTTP ' . $http_code);
        }

        if ($http_code >= 400 && !isset($response['success'])) {
            $response['success'] = false;
            $this->error('Max API error, HTTP ' . $http_code . ': ' . $raw);
        } elseif (($response['success'] ?? true) === false) {
            $this->error('Max API error: ' . ($response['code'] ?? '') . ' ' . ($response['message'] ?? ''));
        }

        return $response;
    }

    protected function fail($code, $message) {
        $this->error($message);
        return ['success' => false, 'code' => $code, 'message' => $message];
    }

    /**
     * Was the request successful? (checks both the HTTP code and the success field)
     */
    public function is_ok($response) {
        if (!is_array($response)) {
            return false;
        }
        if (isset($response['success'])) {
            return $response['success'] === true;
        }
        return !isset($response['code']);
    }

    public function get($resource, $query = []) {
        return $this->request("GET", $resource, $query);
    }

    public function post_json($resource, $body = [], $query = []) {
        return $this->request("POST", $resource, $query, $body);
    }

    public function put_json($resource, $body = [], $query = []) {
        return $this->request("PUT", $resource, $query, $body);
    }

    public function delete($resource, $query = []) {
        return $this->request("DELETE", $resource, $query);
    }

    // ------------------------------------------------------------------
    // Bot
    // ------------------------------------------------------------------

    public function get_me() {
        return $this->get("me");
    }

    // ------------------------------------------------------------------
    // Messages
    // ------------------------------------------------------------------

    /**
     * Send a message.
     *
     * @param array $target ['user_id' => N] for a direct dialog or ['chat_id' => N] for a chat
     * @param string|null $text up to 4000 characters
     * @param array $extra optional: 'keyboard' (result of keyboard()), 'attachments' (array),
     *                     'format' ("markdown"|"html"), 'notify' (bool), 'link' (['type' => 'reply', 'mid' => ...]),
     *                     'disable_link_preview' (bool)
     * @return array API response: on success ['message' => [...]]
     */
    public function send_message($target, $text, $extra = []) {
        $query = [];
        if (isset($target['user_id'])) {
            $query['user_id'] = $target['user_id'];
        }
        if (isset($target['chat_id'])) {
            $query['chat_id'] = $target['chat_id'];
        }
        if (isset($extra['disable_link_preview'])) {
            $query['disable_link_preview'] = $extra['disable_link_preview'];
        }
        if (!$query) {
            return $this->fail('arguments', 'send_message: user_id or chat_id required');
        }

        $body = $this->message_body($text, $extra);

        return $this->post_json("messages", $body, $query);
    }

    /**
     * Edit a message (e.g. remove the keyboard: 'attachments' => []).
     */
    public function edit_message($mid, $text, $extra = []) {
        $body = $this->message_body($text, $extra);
        return $this->put_json("messages", $body, ['message_id' => $mid]);
    }

    public function delete_message($mid) {
        return $this->delete("messages", ['message_id' => $mid]);
    }

    protected function message_body($text, $extra) {
        $body = [];
        if ($text !== null) {
            $body['text'] = $text;
        }
        $attachments = null;
        if (isset($extra['attachments'])) {
            $attachments = $extra['attachments'];
        }
        if (isset($extra['keyboard'])) {
            $attachments = $attachments ?: [];
            $attachments[] = $extra['keyboard'];
        }
        if ($attachments !== null) {
            $body['attachments'] = $attachments;
        }
        foreach (['format', 'notify', 'link'] as $field) {
            if (isset($extra[$field])) {
                $body[$field] = $extra[$field];
            }
        }
        return $body;
    }

    /**
     * Answer a callback button press (clears the "loading" state of the button).
     * $message - optional, new content of the message with the button (e.g. ['text' => '...']).
     */
    public function answer_callback($callback_id, $message = null) {
        $body = [];
        if ($message !== null) {
            $body['message'] = $message;
        }
        return $this->post_json("answers", $body, ['callback_id' => $callback_id]);
    }

    // ------------------------------------------------------------------
    // Keyboards
    // ------------------------------------------------------------------

    /**
     * Build an inline_keyboard attachment. $rows is an array of rows, a row is an array of buttons.
     * Limits: up to 7 buttons per row (up to 3 if the row has link/open_app/request_contact/request_geo_location),
     * up to 30 rows, button text 1-128 characters.
     */
    public function keyboard($rows) {
        return [
            'type' => 'inline_keyboard',
            'payload' => ['buttons' => array_values($rows)],
        ];
    }

    public function btn_callback($text, $payload, $intent = null) {
        $b = ['type' => 'callback', 'text' => $text, 'payload' => strval($payload)];
        if ($intent) {
            $b['intent'] = $intent; // positive | negative | default
        }
        return $b;
    }

    public function btn_link($text, $url) {
        return ['type' => 'link', 'text' => $text, 'url' => $url];
    }

    public function btn_contact($text) {
        return ['type' => 'request_contact', 'text' => $text];
    }

    // The name of the 'quick' flag (send without confirmation) should be checked against the docs: it was not shown on the POST /messages page
    public function btn_geo($text, $quick = false) {
        return ['type' => 'request_geo_location', 'text' => $text, 'quick' => $quick];
    }

    public function btn_message($text) {
        return ['type' => 'message', 'text' => $text];
    }

    // ------------------------------------------------------------------
    // Subscription (webhook) and long polling
    // ------------------------------------------------------------------

    /**
     * Enable the webhook. URL must be https on port 443; the certificate must come from a trusted CA or Mintsifry.
     * $secret: 5-256 characters [a-zA-Z0-9_-]; Max sends it in the X-Max-Bot-Api-Secret header.
     */
    public function subscribe($url, $secret = null, $update_types = null) {
        $body = ['url' => $url];
        if ($update_types) {
            $body['update_types'] = array_values($update_types);
        }
        if ($secret) {
            $body['secret'] = $secret;
        }
        return $this->post_json("subscriptions", $body);
    }

    public function unsubscribe($url) {
        return $this->delete("subscriptions", ['url' => $url]);
    }

    public function get_subscriptions() {
        return $this->get("subscriptions");
    }

    /**
     * Long polling (development only; does not work together with a webhook).
     * Pass the marker from the previous response to avoid receiving the same updates again.
     */
    public function get_updates($marker = null, $timeout = 30, $limit = 100, $types = null) {
        $query = ['limit' => $limit, 'timeout' => $timeout];
        if ($marker !== null) {
            $query['marker'] = $marker;
        }
        if ($types) {
            $query['types'] = $types;
        }
        // The request holds the connection for $timeout seconds - extend the overall cURL timeout
        $old = $this->timeout;
        if ($this->timeout > 0 && $this->timeout < $timeout + 10) {
            $this->timeout = $timeout + 10;
        }
        $rv = $this->get("updates", $query);
        $this->timeout = $old;
        return $rv;
    }

    // ------------------------------------------------------------------
    // Receiving updates
    // ------------------------------------------------------------------

    /**
     * Verify the webhook secret (X-Max-Bot-Api-Secret header). Constant-time comparison.
     */
    public function verify_webhook_secret($secret) {
        $name = 'HTTP_' . strtoupper(str_replace('-', '_', $this->secret_header));
        $got = $_SERVER[$name] ?? '';
        if (!$secret || !is_string($got) || $got === '') {
            return false;
        }
        return hash_equals(strval($secret), $got);
    }

    /**
     * Read the incoming update from the request body (JSON). Returns an array or null.
     */
    public function read_update($raw = null) {
        if ($raw === null) {
            $raw = file_get_contents("php://input");
        }
        $update = json_decode($raw, true);
        return is_array($update) ? $update : null;
    }

    /**
     * Normalized update data, so that project code does not depend on the JSON shape.
     *
     * @return array ['type', 'user_id' (who to reply to in a dialog), 'chat_id', 'text', 'mid', 'callback_id',
     *                'payload', 'contact' (vcf_info/hash/max_info) or null, 'user' (sender), 'is_dialog']
     */
    public function parse_update($update) {
        $type = $update['update_type'] ?? '';
        $r = [
            'type' => $type,
            'user_id' => null, 'chat_id' => null, 'text' => null, 'mid' => null,
            'callback_id' => null, 'payload' => null, 'contact' => null, 'user' => null,
            'is_dialog' => false, 'is_bot' => false,
        ];

        if ($type == 'message_created' || $type == 'message_edited') {
            $m = $update['message'] ?? [];
            $r['user'] = $m['sender'] ?? null;
            $r['user_id'] = $m['sender']['user_id'] ?? null;
            $r['is_bot'] = !empty($m['sender']['is_bot']);
            $r['chat_id'] = $m['recipient']['chat_id'] ?? null;
            $r['is_dialog'] = ($m['recipient']['chat_type'] ?? '') == 'dialog';
            $r['text'] = $m['body']['text'] ?? null;
            $r['mid'] = $m['body']['mid'] ?? null;
            foreach (($m['body']['attachments'] ?? []) as $att) {
                if (($att['type'] ?? '') == 'contact') {
                    $r['contact'] = $att['payload'] ?? null;
                }
            }
        } elseif ($type == 'message_callback') {
            $cb = $update['callback'] ?? [];
            $r['callback_id'] = $cb['callback_id'] ?? null;
            $r['payload'] = $cb['payload'] ?? null;
            $r['user'] = $cb['user'] ?? null;
            $r['user_id'] = $cb['user']['user_id'] ?? null;
            $r['chat_id'] = $update['message']['recipient']['chat_id'] ?? null;
            $r['mid'] = $update['message']['body']['mid'] ?? null;
            $r['is_dialog'] = ($update['message']['recipient']['chat_type'] ?? '') == 'dialog';
        } elseif ($type == 'bot_started') {
            $r['user'] = $update['user'] ?? null;
            $r['user_id'] = $update['user']['user_id'] ?? null;
            $r['chat_id'] = $update['chat_id'] ?? null;
            $r['payload'] = $update['payload'] ?? null;
            $r['is_dialog'] = true;
        }

        return $r;
    }

    public function error($message) {
        error_log($message);
    }
}
