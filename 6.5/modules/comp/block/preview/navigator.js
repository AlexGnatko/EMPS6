var vuev, app;

emps_scripts.push(function(){
    vuev = new Vue();
    app = new Vue({
        el: '#preview-app',
        data: function() {
            return {
                current: {},
            };
        },
        mounted: function(){
            let e = $("#preview-app")[0];
            e.addEventListener("mousemove", this.mousemove);
            e.addEventListener("mouseout", this.mouseout);
            e.addEventListener("click", this.click);
        },
        methods: {
            mouseout: function(e) {
                this.$refs.l1.style.display = "none";
                this.$refs.l2.style.display = "none";
                this.$refs.l3.style.display = "none";
                this.$refs.l4.style.display = "none";
            },
            mousemove: function(e) {
                if (this.current.id === e.target.id) {
                    return;
                }
                this.$refs.l1.style.display = "none";
                this.$refs.l2.style.display = "none";
                this.$refs.l3.style.display = "none";
                this.$refs.l4.style.display = "none";
                if (e.target.id == "preview-app") {
                    this.current = {};
                    return;
                }
                let c = e.target;
                let id = c.id;
                while (!this.valid_id(id)) {
                    c = ($(c).parent())[0];
                    if (!c) {
                        return;
                    }
                    id = c.id;
                }
                this.current = c;
//                console.log("CURRENT", c);

                let f = this.$refs.l1;
                f.style.top = this.current.offsetTop + "px";
                f.style.left = this.current.offsetLeft + "px";
                f.style.width = this.current.offsetWidth + "px";
                f.style.height = this.current.offsetHeight + "px";
                f.style.display = "block";
                let p = this.highlight_parent(($(this.current).parent())[0], 2);
                if (!p) {
                    return;
                }
                this.highlight_parent(($(p).parent())[0], 3);
            },
            highlight_parent: function(o, level) {
                if (!o) {
                    return;
                }
                let c = o;
                let id = c.id;
                while (!this.valid_id(id)) {
                    c = ($(c).parent())[0];
                    if (!c) {
                        return;
                    }
                    id = c.id;
                }
                let f = this.$refs['l' + level];
                f.style.top = c.offsetTop + "px";
                f.style.left = c.offsetLeft + "px";
                f.style.width = c.offsetWidth + "px";
                f.style.height = c.offsetHeight + "px";
                f.style.display = "block";
//                console.log("F", f.style);
                return c;
            },
            valid_id: function(id) {
                if (!id) {
                    return false;
                }
                let x = id.split("el_");
                if (x[1]) {
                    return true;
                }
                return false;
            },
            click: function(e) {
                if (!this.current.id) {
                    return;
                }
                window.parent.postMessage({code: 'click', id: this.current.id}, '*');
                //alert(this.current.id);
            }
        }
    });
    EMPS.load_css("/mjs/comp-block-preview/preview.css");
});

