# Application Design Choices

## Why no build stage?

Using a build tool like Vite or Webpack has its own numerous advantages, and yet it's not
used in this project. So far, the drawbacks of using a build tool outweigh its advantages.
It doesn't mean, though, that in future we wouldn't consider employing a build-tool approach
in some way.

#### *The drawbacks of taking the build tool approach are:*

### Bulkier JS applications in bigger projects

The build generates one big JS file that contains the entire single-page application (SPA)
with all its code and HTML templates. This means that no matter which part of the big
application you are using, the browser is running the entire application at any given 
moment.

Older solutions like Webpack used to be inefficient in terms of compile time from detecting
a change to delivering the updated build, newer solutions like Vite are more efficient
than that, delivering the app in pieces during the development stage, thus not having to
rebuild it entirely. But in production, it is still one big JS file.

And yes, one big file is more efficient in terms of page load than 50 small files, but
if the end user came for just 5% of the functionality, the 95% of the code will be
useless to them, and yet it will still be parsed in the browser and occupy RAM and load
the CPU.

#### How it's done in EMPS

EMPS projects assemble an application from multiple parts - Vue
mixins and components, right in the client's browser. There is no compile or build stage,
all that is done is loading some files (mostly JS, CSS, and some HTML Vue templates) and
parsing them in the browser on page load. If the client is staying within the same
page (controller), for example, `/projects/`, they do not navigate to other pages of the
website. If they need to get inside a project, i.e. open the URL like `/projects/123/-/info/`,
this is done by the mini-SPA on the `/projects/` page, the current address in the browser
address bar is changed without the reloading of the page (using `window.history.pushState`), 
and the mini-SPA reflects the change in the current URL by loading the proper project 
and showing the "edit project" part of the interface.

So, in an EMPS project, the various pages such as `/projects/`, `/tasks/`, `/my/`
are in effect mini-SPAs which might share and reuse the same components and mixins, 
but in a different combination. Whenever a mixin or a component is required by the mini-SPA,
the browser takes its source code from cache, so no actual server load is incurred, only
if the client decides to hard-refresh the page, or if it's a new client, or if the
source code has changed and the components need to get refreshed. And there aren't that
many source files in any mini-SPA.

What that means is that the browser runs only the code it needs to run on that particular
page. Instead of building one big JS file for all uses within the project, EMPS assembles
every mini-SPA on every page from reusable components that are mostly stored in the
client cache. Every mini-SPA contains only those functions that are needed on that 
particular page.

### Influence of the node.js approach  

In a node.js application, everything is easy, all dependencies are in the `node_modules` 
folder and can be easily referred using the `import` directive. There is a wide choice 
of dependencies available through `npm`, anyone can quickly build any kind of application
by installing the proper dependencies and linking them together to quickly build a new
application.

This is not a drawback by itself, but it teaches people to rely on node.js modules
so much that it becomes hard to use JS in the browser without the build stage.
Although modern browsers do support modules ([JavaScript modules](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Modules)),
using them is not easy without a build tool.

Traditionally, JavaScript has been used in browsers in a much different way. But then, 
the author of node.js decided to use the highly efficient Google JS engine for the Chrome
browser in a back-end role, and that's how JS ventured into the server side and then
made the full circle and returned to the browser with all the baggage it accumulated in 
the server-side world, good or not so good.

### No HTML pages

In SPA's built with a build tool, the HTML of the page is just a single `div` into which
new elements get added by JS after the application is started in the browser. So, HTML
pages in the traditional sense cease to exist, there's no data in the HTML files, search
engines can't index them, you can't open the pages in a text-only browser, you get an
empty page if JavaScript is turned off, etc. Though this is not a big deal in most cases,
sometimes the plain source-code of the HTML pages is important for SEO purposes.

At first, the SPA build tools and frameworks ignored this problem, but then they still
had to add some kinds of server-side rendering functionality to address the use cases
where it's needed.

#### How it's done in EMPS

EMPS never departed from the concept of having meaningful HTML code in the pages.
EMPS mini-SPA's can co-exist with the HTML code of the page, they are assembled using 
the HTML code, the HTML code of the page can contain the Vue.js template of the mini-SPA. 

For example, a video page could contain the transcript of the video in the HTML code itself,
making it **indexable by search engines**. When the web server serves such a page, it invokes
the page controller PHP script on the server, which generates the HTML code of the page.
Then, the mini-SPA gets initialized and starts using the existing HTML (which doubles as
a Vue.js app HTML template) to enhance it with interactivity.

#### *The advantages of the build tool approach are:*

### Mainstream adoption

It looks like everyone's using build tools to create single-page browser applications. 
There are countless good and bad examples to learn from, there is a lot of code to 
copy and paste, ChatGPT perfectly knows how to use build tools and can guide any 
newbee through the entire process.

### Using Vue Single-File Components and Composition API

It's hard to argue that single-page components in Vue.js are handy, and that the
Composition API is pretty neat. So far, it's not possible to use them in EMPS projects,
but I will keep exploring solutions to enable single-page components and Composition API
in EMPS Vue.js mini-SPAs.

### Writing code in Typescript

Typescript isn't natively supported by modern browsers, so a build tool is needed in any
case. Some developers highly value Typescript for its more advanced syntax and strict data
types. But, the bottom line is that it's still compiled into JS by the build tool.

### Optimizations

Build tools are supposed to do a good job at optimizing the resulting JS, CSS and HTML code 
so that it doesn't bring unused code to the browser. Whether they do that optimization
effectively depends on the build tool and the structure of the project, very often
the effects of such optimizations are only marginal.


----


## Why no separate back-end and front-end?

Effectively, the front-end and back-end are separate here, it's just their source code 
co-exists in a single repository. However, the back-end is used to define the composition
of the front-end, so there is more interlink between the front-end and the back-end
than in other cases, when a purely front-end JS framework uses a back-end server only
to retrieve and store its data.

### Roles of the back-end

The back-end of an EMPS project is a classical `nginx` + `php-fpm` combination. `nginx` is
the robust industrial-grade web server which interacts directly with the user's browser
to send them all the data they need to use the website. Some of that data are static
files in the document root directory - such as images, CSS files, SVG files, media files,
all the rest is data generated by PHP scripts.

#### PHP scripts

Whenever a plain file or a directory can not be found in the document root, the `index.php`
file gets invoked. So, all non-file requests are served by the EMPS
framework which is invoked from that `index.php` file. This is done by `nginx` URL 
rewriting. The HTTP request is sent to a `php-fpm` worker (PHP FastCGI Manager), 
the call is fast and efficient, the PHP-FPM workers utilize PHP opcode caching and 
can handle PHP scripts nearly as efficiently as a node.js worker would handle its 
request, and maybe even as efficiently as a dedicated program written in Go. 
The latest versions of PHP 8 are highly performant.

Critics might say that PHP can't be as efficient as node.js or Go in handling multiple
HTTP requests at a time, but I think that the nginx and PHP-FPM combination helps a lot
at bringing the multithreading efficiency to the system.

Go and JS (node.js) have their strengths in subroutines, the programming trick that lets
those programming languages execute multiple parallel virtual threads of code on a
single CPU thread. This is highly efficient in handling a high number of low-intensity
operations in parallel, which is the requirement of web applications' back-end.

However, for the sequential nature of a typical HTTP request, PHP has consistently been
among the fastest scripting languages and remains so. A single atomic **HTTP request
is a linear process** — things happen one after another, and PHP's design maps perfectly
to this model. Node.js is a capable and respected platform, but it brings inherent
complexity: asynchronous thinking, promises everywhere, and the mental overhead of
fitting async patterns into what is fundamentally a sequential request/response cycle.
PHP's simplicity here is a strength: "press a button — get a result." Go is a compiled
language generating binary executables and operates in a different category entirely.

The parallelism of a PHP-enabled web back-end is achieved through nginx and PHP-FPM,
each request is handled in its dedicated CPU thread.

From the developer standpoint, PHP is much easier to use and much more focused on
handling HTTP requests than other programming languages used in the same role (JS, 
Python, Java, Perl, etc.), and this advantage outweighs its other aspects that
some developers might call drawbacks. The lack of subroutines is more of an
advantage from the developer's standpoint, and it makes the code much easier to understand
and debug. The lack of advanced syntax perks used in Python is not a drawback at all,
as most average programmers don't understand those perks anyway. The lack of the 
requirement to declare data types makes things easier, not harder. In the new versions
of PHP, you can use strict typing if you so desire. So, my opinion is that there is
no strong case against using PHP, so why not use it if we have been doing that since
the 1990's. The language is evolving and becoming more performant and efficient.

#### Front-end composition

The PHP back-end (EMPS framework) also handles code and data delivery for the in-browser
application. The request to a URL like `/projects/` first generates the HTML code of
that page. Then, the browser finds out which other components need to be fetched (images,
CSS files, JS files, etc.), which parts of the JS mini-SPA can be taken directly from the
page HTML, fetches the missing parts from the server or the cache, and assembles everything
into the mini-SPA that handles this page.

This might look like an overly elaborate process, much more complex than just loading and 
running a single JS file of a compiled SPA, but, as I've already pointed out earlier,
this provides flexibility as to which parts of the JS application are needed on this
particular page, and each of the individual code parts (JS files, CSS files) are
cached in the browser, so that subsequent calls don't actually require any re-fetching
of the code files. Besides, a compiled SPA still has to be interpreted by the browser,
and the all-in-one SPA file is much more complex and hard on the CPU than a smaller 
collection of small mini-SPA components.

#### Data connection to the front-end

Once a mini-SPA is up and running, it might need to load some additional JSON data
or post some data to the server. This is mostly done by sending AJAX requests from
the JS application back to the server. I prefer using `axios` for the AJAX calls. 

EMPS can handle simple AJAX request right in the PHP code of the page which hosts the
mini-SPA. For example, if the mini-SPA is `/projects/`, it can call the same URL, but
with a GET parameter, e.g. `/projects/?list=1`. Then, the PHP controller of the 
`/projects/` page, which is at `modules/projects/projects.php` on the web server,
can have a piece of code somewhere in the beginning that goes like this:

```php
if ($_GET['list'] ?? false) {
    $lst = $obj->list_projects($q);
    $emps->json_ok(['lst' => $lst]); exit;
}
```

This will supply the client-side mini-SPA with the `lst` array which contains data
to display in the projects list. There can also be additional data in the response,
such as the current pagination state and helper links, and the current filter settings.

Then, if the mini-SPA needs to send some data back to the server or invoke some action
on the server, it can do a GET or a POST AJAX request like this:

```js
axios.post("./?", {post_update: true, payload: this.row}).then(response => {
    let data = response.data;
    if (data.code == 'OK') {
        toastr.success("Data updated!");
    }
});
```

This request will be handled by the server like this:

```php
if ($_POST['post_update'] ?? false) {
    $payload = $_POST['payload'];
    $rv = $obj->update_project($project_id, $payload);
    if ($rv) {
        $emps->json_ok();
    } else {
        $emps->json_error($obj->last_error);
    }
    exit;
}
```

#### Users and access control

User authentication is handled by storing authentication session identifiers to PHP 
sessions. EMPS uses a MySQL-driven PHP session store instead of the standard
file-based store, which makes the process more efficient and allows for longer
session storage times.

Every visitor (including unauthenticated visitors) which demonstrates the ability
to hold and return cookies in HTTP requests is assigned a PHP session identifier
in an HTTP cookie.

On every request, the cookie value is found in the PHP sessions database table,
and the data stored in the session row is loaded into the `$_SESSION` named array.

The session array of an authenticated user holds the identifier of an EMPS session,
if a row with the same identifier is present in the `e_sessions` database table,
the request is considered to be coming from an authenticated user, and it's possible
to determine which user that is, as `user_id` is one of fields in the `e_sessions` table.

What all this means is that once the user is authenticated, their PHP session cookie
lets the server determine if they are logged in and who they are. Browsers send cookies
with all HTTP requests to the same domain. So, a user that authenticated on another
page of the website and got their PHP session identifier and an EMPS session identifier
in their `$_SESSION`, will have all their subsequent requests to the webserver,
including AJAX requests, being treated as authenticated requests.

#### *More information on the EMPS framework*

Please visit the following website for more info on the EMPS framework:

[EMPS Framework](https://emps.ag38.ru/)

----


## What's the database?

The back-end database is MySQL. Most tables run the MyISAM engine, not InnoDB. 

### Why MyISAM? Are we living in 1999?

MyISAM is much faster than anything, if most of the queries it processes are `SELECT`.

My experience with MySQL is that for small and medium-sized projects with predominantly
`SELECT` workloads, MyISAM is faster than InnoDB and simpler to work with — its table
files are straightforward to copy, move, and inspect. InnoDB has its own crash recovery
mechanism and has improved significantly over the years, but when an InnoDB table
file gets severely corrupted, recovery can be difficult. With MyISAM, repair tools
tend to be more straightforward. That said, progress moves forward and InnoDB is
the right choice when you need transactions, foreign keys, or row-level locking.

The drawbacks of MyISAM show up in cases where there are extremely frequent updates 
and lots of table-level locks (there are no row-level locks in MyISAM) - it becomes
much slower and can clog the table lock queue. Another drawback
is that it's impossible to set up a MySQL cluster with MyISAM tables.

In case the project needs to scale its database capabilities due to fast usage growth,
additional or alternative database solutions may need to be considered. But so far,
MySQL with MyISAM does a perfect job for most EMPS projects.
