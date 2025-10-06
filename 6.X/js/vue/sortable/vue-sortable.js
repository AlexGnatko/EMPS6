emps_scripts.push(function() {
    if (EMPS.vue_version() === 2) {
        var vSortable = {}
        var Sortable = typeof require === 'function'
            ? require('sortablejs')
            : window.Sortable

        if (!Sortable) {
            throw new Error('[vue-sortable] cannot locate Sortable.js.')
        }

        // exposed global options
        vSortable.config = {}

        vSortable.install = function (Vue) {
            Vue.directive('sortable',
                {
                    inserted: function (el) {
//                    alert("inserted: " + JSON.stringify(el.options));
                        var sortable = new Sortable(el, el.options)
                        if (this.arg && !this.vm.sortable) {
                            this.vm.sortable = {}
                        }
// Throw an error if the given ID is not unique
                        if (this.arg && this.vm.sortable[this.arg]) {
                            console.warn('[vue-sortable] cannot set already defined sortable id: \'' + this.arg + '\'')
                        }
                        else if (this.arg) {
                            this.vm.sortable[this.arg] = sortable
                        }
                    },
                    bind: function (el, binding) {
                        var options = Object.assign({}, window.emps_sortable_options);
                        options = Object.assign(options, binding.value || {});
                        el.options = options;
                    }
                })
        }

        if (typeof exports == "object") {
            module.exports = vSortable
        } else if (typeof define == "function" && define.amd) {
            define([], function () {
                return vSortable
            })
        } else if (window.Vue) {
            window.vSortable = vSortable
            let app = Vue;
            if (EMPS.vue_version() == 3) {
                app = EMPS.mainapp;
            }
            app.use(vSortable)
        }
    }

    EMPS.vue3_scripts.push(function() {
        function isVue3() {
            try {
                return typeof EMPS !== 'undefined' && EMPS && typeof EMPS.vue_version === 'function'
                    ? (EMPS.vue_version() == 3)
                    : false
            } catch (e) {
                return false
            }
        }

        if (EMPS.vue_version() === 3) {
            var vSortable = {}
            var Sortable = typeof require === 'function'
                ? require('sortablejs')
                : window.Sortable

            if (!Sortable) {
                throw new Error('[vue-sortable] cannot locate Sortable.js.')
            }

            // exposed global options
            vSortable.config = {}

            vSortable.install = (app) => {
                console.log("CALLED INSTALL", app);
                app.directive('sortable',
                    {
                        mounted: (el, binding, vnode) => {
                            var options = el._vSortableOptions || {}
                            var sortable = new Sortable(el, options)
                            el._vSortable = sortable

                            // Find component instance (Vue2: vnode.context, Vue3: binding.instance)
                            var comp = isVue3()
                                ? (binding && binding.instance) // Vue 3 component proxy
                                : (vnode && vnode.context)      // Vue 2 component instance

                            var arg = binding && binding.arg

                            if (arg && comp) {
                                if (!comp.sortable) comp.sortable = {}
                                if (Object.prototype.hasOwnProperty.call(comp.sortable, arg)) {
                                    console.warn("[vue-sortable] cannot set already defined sortable id: '" + arg + "'")
                                } else {
                                    comp.sortable[arg] = sortable
                                }
                            }
                        },
                        beforeMount: (el, binding) => {
                            var base = (typeof window !== 'undefined' && window.emps_sortable_options) || {}
                            var value = binding && binding.value ? binding.value : {}
                            // merge: default <- binding.value
                            el._vSortableOptions = Object.assign({}, base, value)
                        }
                    })
            }

            window.vSortable = vSortable
            let app = EMPS.mainapp;
            app.use(vSortable);
        }
    });

});
