EMPS.vue_component('block-params', '/mjs/comp-block-params/params.vue',
    {
        props: ['value', 'prefix'],
        components: {
            'editor': Editor // <- Important part
        },
        data: function(){
            return {
                emps_tinymce_settings: window.emps_tinymce_settings,
                error: "",
                clipboard: null,
                sublst: [],
            };
        },
        methods: {
            add_row: function(row) {
                if (!(row.value instanceof Array)) {
                    row.value = [];
                }
                row.value.push({type: 'ref', value: 0});
                this.$forceUpdate();
            },
            save: function() {
                this.$emit("save");
            },
            remove_item: function(index, lst) {
                lst.splice(index, 1);
                this.$forceUpdate();
            },
            urlencode: function(x) {
                x = x.replace(/\//gi, '{slash}');
                return x;
            },
            convert_to_raw: function(srow, urow) {

                srow.type = 'raw';
                srow.template = urow.template;

                this.change_template(srow);
            },
            convert_to_ref: function(srow) {

                srow.type = 'ref';
                srow.value = 0;
            },
            change_template: function(srow) {
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
                            srow.value = data.lst;
                            that.$forceUpdate();
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
            copy_to_clipboard: function(srow) {
                this.clipboard = this.copy_array(srow);
            },
            insert_from_clipboard: function(row, si) {
                row.value.splice(si, 0, this.clipboard);
                this.clipboard = null;
                this.$forceUpdate();
            },
            cut_to_clipboard: function(srow, row, si) {
                this.copy_to_clipboard(srow);
                row.value.splice(si, 1);
                this.$forceUpdate();
            }
        },
        computed: {
        },
        watch: {
        },
        mounted: function(){
            this.emps_tinymce_settings.height = 300;
        }
    }
);
