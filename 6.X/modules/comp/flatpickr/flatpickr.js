(function() {

    Vue.component('flatpickr', {
        template: '#flatpickr-component-template',
        props: ['size', 'value', 'hasTime', 'minDate', 'maxDate', 'setclass',
            'hasClock', 'unix', 'mformat',
            'dateFormat', 'placeholder', 'asButton'],
        data: function(){
            return {
                picker: null,
                set_class: '',
                config: window.emps_flatpickr_options,
            };
        },
        methods: {
            redraw: function(newConfig) {
                this.picker.config = Object.assign(this.picker.config, newConfig);
                this.picker.config.minDate = this.minDate;
                this.picker.config.maxDate = this.maxDate;
                this.picker.config.disableMobile = true;
                this.picker.redraw();
                this.picker.jumpToDate();
            },
            set_date: function(newDate, oldDate) {
//                alert(newDate + " / " + oldDate);
                if (this.unix) {
                    if ((newDate !== oldDate) && newDate !== undefined && newDate != '' && newDate != 0 && newDate != null) {
                        let m = moment.unix(newDate);
                        let fdate = m.format(this.mformat);
                        this.picker.setDate(fdate);
                        console.log("Setting date (unix): " + newDate + " (" + fdate + ") / " + oldDate);
                    }
                    if (newDate === undefined || newDate == '') {
                        this.picker.clear();
                        $(this.$refs.input).val('');
                    }
                } else {
                    if ((newDate !== oldDate) && newDate !== undefined && newDate != '') {
                        this.picker.setDate(newDate);
                        console.log("Setting date: " + newDate + " / " + oldDate);
                    }
                    if (newDate === undefined || newDate == '') {
                        this.picker.clear();
                        $(this.$refs.input).val('');
                    }
                }

            },
            date_updated: function(selectedDates, dateStr) {
                if (dateStr !== undefined && dateStr != '') {
                    console.log("Date updated: "  + dateStr);
                    if (this.unix) {
                        var date = moment(dateStr, this.mformat);
                        var edt = date.unix();
                        console.log("Emitting " + edt);
                        this.$emit("input", edt);
                    } else {
                        this.$emit("input", dateStr);
                    }

                }
            }
        },
        mounted: function(){
            this.config.minDate = this.minDate;
            this.config.maxDate = this.maxDate;
            if (!this.picker) {
                this.config.onValueUpdate = this.date_updated;
                this.config.disableMobile = true;
                var dateFormat = "d.m.Y";
                if (this.dateFormat !== undefined) {
                    dateFormat = this.dateFormat;
                }
                if (this.config.dateFormat !== undefined) {
                    dateFormat = this.config.dateFormat;
                }
                if (this.hasTime) {
                    this.config.enableTime = true;
                    if (dateFormat.indexOf("H:i") == -1) {
                        this.config.dateFormat = dateFormat + " H:i";
                    } else {
                        this.config.dateFormat = dateFormat;
                    }

                } else {
                    this.config.enableTime = false;
                    this.config.dateFormat = dateFormat;
                }

                this.picker = flatpickr(this.$refs.input, this.config);
            }
            //setTimeout(this.set_date, 100, this.value);
            this.set_date(this.value);
            this.$watch('minDate', this.redraw);
            this.$watch('maxDate', this.redraw);
            this.$watch('config', this.redraw);
            this.$watch('value', this.set_date);
            this.$watch('setclass', function(newval, oldval) {
                this.set_class = newval;
            });
        }
    });


})();
