<div>
    <template v-for="(row,idx) in value.value">
        <div class="mb-3">
            <label class="label">{{ row.title }}:</label>
            <template v-if="row.type == 'c'">
                <input type="text" v-model="row.value" @keydown.enter="save" class="input" :placeholder="row.default" />
            </template>
            <template v-if="row.type == 'h'">
                <editor v-model="row.value" :id="'param_html_' + prefix + '_' + idx" :init="emps_tinymce_settings"></editor>
            </template>
            <template v-if="row.type == 't'">
                <textarea class="textarea" v-model="row.value" rows="3"></textarea>
            </template>
            <template v-if="row.type.substr(0, 1) == 'a'">
                <div class="panel mb-3">
                    <div class="panel-block is-block">
                        <template v-for="(srow,si) in row.value">
                            <template v-if="srow.type == 'ref'">
                                <div class="columns is-mobile">
                                    <div class="column">
                                        <div class="control">
                                            <selector
                                                    v-model="srow.value"
                                                    title="Блок"
                                                    :has-extra="true"
                                                    :placeholder="row.template"
                                                    :search="true"
        :type="'e_blocks' + ((srow.template !== undefined && srow.template != '')?'|template=' + urlencode(srow.template):'')">
                                            </selector>
                                        </div>
                                    </div>

                                    <div class="column is-narrow">
                                        {{capture assign="clipbtns"}}
                                        <template v-if="clipboard === null">
                                            <button type="button"
                                                    @click="cut_to_clipboard(srow, row, si)"
                                                    class="button is-info is-light">
                                                <i class="fa fa-scissors"></i>
                                            </button>
                                            <button type="button"
                                                    @click="copy_to_clipboard(srow)"
                                                    class="button is-info is-light">
                                                <i class="fa fa-clone"></i>
                                            </button>
                                        </template>
                                        <template v-else>
                                            <button type="button"
                                                    @click="insert_from_clipboard(row, si)"
                                                    class="button is-info is-light">
                                                <i class="fa fa-clipboard"></i>
                                            </button>
                                        </template>
                                        {{/capture}}

                                        <button type="button"
                                                @click="convert_to_raw(srow, row)"
                                                class="button is-info is-light">
                                            <i class="fa fa-pencil"></i>
                                        </button>
                                        {{$clipbtns}}
                                        <button type="button"
                                                @click="remove_item(si, row.value)"
                                                class="button is-danger is-light">
                                            <i class="fa fa-remove"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template v-if="srow.type == 'raw'">
                                <div class="columns is-mobile">
                                    <div class="column">
                                        <div class="field has-addons">
                                            <div class="control is-expanded">
                                                <input type="text" v-model="srow.template"
                                                       @keydown.enter="change_template(srow)"
                                                       class="input" />
                                            </div>
                                            <div class="control">
                                                <button
                                                        @click="change_template(srow)"
                                                        class="button is-info is-light" type="button">
                                                    <i class="fa fa-check"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="column is-narrow">
                                        <button type="button"
                                                @click="srow.expanded = !srow.expanded"
                                                class="button is-info is-light">
                                            <i v-if="!srow.expanded" class="fa fa-chevron-down"></i>
                                            <i v-else class="fa fa-chevron-up"></i>
                                        </button>
                                        <button type="button"
                                                @click="convert_to_ref(srow)"
                                                class="button is-info is-light">
                                            <i class="fa fa-link"></i>
                                        </button>
                                        {{$clipbtns}}
                                        <button type="button"
                                                @click="remove_item(si, row.value)"
                                                class="button is-danger is-light">
                                            <i class="fa fa-remove"></i>
                                        </button>
                                    </div>
                                </div>

                                <block-params v-if="srow.expanded" v-model="value.value[idx].value[si]"
                                              @clipboard="emit_clipboard"
                                              :clipboard="clipboard"
                                              :prefix="prefix + '_' + idx + '_' + si" @save="save"></block-params>
                            </template>
                        </template>
                        <button type="button" @click="add_row(row)" class="button is-primary is-light">Добавить элемент</button>
                        <button type="button"
                                v-if="clipboard !== null"
                                @click="insert_from_clipboard(row, -1)"
                                class="button is-info is-light">
                            <i class="fa fa-clipboard"></i>
                        </button>

                    </div>
                </div>
            </template>
        </div>
    </template>
</div>