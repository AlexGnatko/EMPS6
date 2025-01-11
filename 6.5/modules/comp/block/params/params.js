EMPS.vue_component('block-params', '/mjs/comp-block-params/params.vue',
    {
        props: ['value', 'prefix', 'clipboard', 'mode', 'nidx', 'depth'],
        mixins: [EMPS_common_mixin],
        components: {
            'editor': Editor // <- Important part
        },
        data: function(){
            return {
                emps_tinymce_settings: window.emps_tinymce_settings,
                error: "",
                sublst: [],
                erow: {},
                arow: {},
                atrow: {},
                addmode: "raw",
            };
        },
        methods: {
            add_row: function(row) {
                if (!(row.value instanceof Array)) {
                    row.value = [];
                }
                let srow = {type: 'raw', value: 0, template: row.template, expanded: true};
                row.value.push(srow);
                this.change_template(srow);
                this.$forceUpdate();
            },
            add_row_start: function(row) {
                if (!(row.value instanceof Array)) {
                    row.value = [];
                }
                console.log("ADD ROW START", row, this.depth);
                this.atrow = row;
                let srow = {type: 'raw', value: 0, template: row.template, expanded: true};
                this.arow = srow;
                this.open_modal("addBlockModal");
            },
            add_row_finish: function() {
                this.arow.type = this.addmode;
                this.atrow.value.push(this.arow);
                console.log("ADDING", this.arow);
                this.change_template(this.arow, this.save, this.arow.value);
            },
            submit_new_block: function() {
                this.add_row_finish();
                this.close_modal("addBlockModal");
            },
            save: function() {
                this.$emit("save");
            },
            remove_item: function(index, lst) {
                if (confirm(window.strings.do_delete)) {
                    lst.splice(index, 1);
                    this.$forceUpdate();
                }
            },
            urlencode: function(x) {
                x = x.replace(/\//gi, '{slash}');
                return x;
            },
            convert_to_raw: function(srow) {

                srow.type = 'raw';
                srow.template = "blocks/plain";

                this.change_template(srow);
            },
            convert_to_ref: function(srow) {

                srow.type = 'ref';
                srow.value = 0;
            },
            change_template: function(srow, after = null, oldvalue = null) {
                var that = this;
                var row = {};
                row.post_get_params = 1;
                row.payload = srow.value;


                var template = "blocks{slash}plain";
                if (srow.template !== undefined) {
                    template = this.urlencode(srow.template);
                }

                axios
                    .post("/comp-block-params/" + template + "/", row)
                    .then(function(response){
                        var data = response.data;

                        if(data.code == 'OK'){
                            if (oldvalue === null) {
                                srow.value = data.lst;
                            }

                            srow.template_title = data.title;
                            that.$forceUpdate();
                            if (after !== null) {
                                after.call();
                            }
                        } else {
                            toastr.error(data.message);
                        }
                    });

            },
            copy_array: function(a) {
                if (!a) {
                    return [];
                }
                return JSON.parse(JSON.stringify(a));
            },
            emit_clipboard: function(data) {
                this.$emit("clipboard", data);
                this.clipboard = data;
                this.$forceUpdate();
            },
            emit_edit: function(data) {
                this.$emit("edit", data);
            },
            emit_add: function(data) {
                this.$emit("add", data);
            },
            copy_to_clipboard: function(srow) {
                this.emit_clipboard(this.copy_array(srow));
            },
            insert_from_clipboard: function(row, si) {
                console.log(JSON.stringify(row));
                if (si == -1) {
                    if (!(row.value instanceof Array)) {
                        row.value = [];
                    }
                    row.value.push(this.clipboard);
                } else {
                    row.value.splice(si, 0, this.clipboard);
                }

                this.emit_clipboard(null);
            },
            cut_to_clipboard: function(srow, row, si) {
                this.copy_to_clipboard(srow);
                row.value.splice(si, 1);
            },
            handle_edit: function(row) {
                this.erow = row;
                this.open_modal("editParamModal");
            },
            submit_block_form: function() {
                this.save();
                this.close_modal("editParamModal");
            },
            toggle_expanded: function(srow) {
                srow.expanded = !srow.expanded;
                if (!srow.expanded) {
                    this.set_expanded(srow, false);
                }
                this.$forceUpdate();
            },
            set_expanded: function(srow, value) {
                srow.expanded = value;
                if (Array.isArray(srow.value)) {
                    for (let v of srow.value) {
//                        console.log("setting expanded: ", v.template, v.template_title, v);
                        this.set_expanded(v, value);
                    }
                }
            },
            find_block_by_id: function(id) {
                let x = id.split("_");
                x.shift();
                let lst = this.value.value;
                console.log("LST", lst);
                return this.find_block(lst, x);
            },
            find_block: function(lst, ids) {
                let id = ids.shift();
                console.log("IDS", id, ids);
                for (let i = 0; i < lst.length; i++) {
                    let param = lst[i];
                    if (param.type.substr(0, 1) == 'a') {
                        for (let k = 0; k < param.value.length; k++) {
                            let subitem = param.value[k];
                            let idx = k + 1;
                            console.log("IDX / ID / item", idx, id, subitem);
                            if (idx == id) {
                                if (ids.length == 0) {
                                    return subitem;
                                }
                                return this.find_block(subitem.value, ids);
                            }
                        }
                    } else {
                        console.log("unusable param", param);
                    }
                }
            },
            message_handler: function(event) {
                if (this.depth > 0) {
                    return;
                }
                let data = event.data;
                if (data.code == 'click') {
                    console.log("CLICK", data, this.prefix);
                    let row = this.find_block_by_id(data.id);
                    if (!row) {
                        return;
                    }
                    console.log("FOUND", row);
                    this.emit_edit(row);
                }
            }

        },
        computed: {
        },
        watch: {
        },
        created: function () {
            window.addEventListener('message', this.message_handler);
        },
        destroyed: function () {
            window.removeEventListener('message', this.message_handler);
        },
        mounted: function(){
            if (this.depth == 0) {
                this.$on("edit", this.handle_edit);
                this.$on("add", this.add_row_start);
            }
            this.emps_tinymce_settings.height = 300;
        }
    }
);
