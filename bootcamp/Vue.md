# Using Vue in EMPS Projects

## No build step

By now you should have read [Application Design Choices](App-Design.md)
section and learned that there is no build step, no Vite, no webpack, 
no esbuild. If you relied on `import` statements, `.vue` files in the
original sense (Single-File Components) - those things aren't used in
EMPS projects. The Composition API isn't used as well, though it's not 
impossible. All apps and components use Options API.

## Non-Conventional Approach

EMPS supports both Vue 2 and Vue 3 projects, and the approach to assembling components
differs between them in a few important ways. An AI assistant (such as Claude) that has
carefully studied this document and understands:

- that there is no build step and no SPA compilation,
- how components are registered via `EMPS.vue_component_direct` (Vue 3) vs `Vue.component` (Vue 2),
- how mixins are pushed to `main_app_mixins` and assembled into the `mainapp`,
- how MJS serves JS/CSS/Vue files from the `modules` tree,
- the difference between Option A (inline template) and Option B (external `.vue` file),

...can be trusted to write Vue components and mixins in the EMPS style correctly,
without introducing build-tool patterns or SPA conventions that don't apply here.

Fortunately, there isn't much to learn — only a few important differences from the
ordinary way of using Vue.

## Most Important Principles

Always keep in mind these things:

* **The application is assembled in the browser**. The server side
serves the HTML code of the page (which can be different for different
pages or user interaction states), the HTML code invokes JS code, the
JS code uses DOM to load missing HTML pieces if necessary, preloads
components, configures the Vue application and starts it.
* **Do not assume that every component is accessible from anywhere**. As
there is no build step, there is no all-encompassing compiled Vue app.
Every website page is its own Vue application, so, before trying to use
a component, make sure you have included it on the page.
* **Be aware of Smarty in addition to Vue**. In most cases, the HTML
templates for Vue are rendered by Smarty templates. More info
on this page - 
[Combining Smarty and Vue.js in EMPS Templates](https://emps.ag38.ru/docs/smarty-vue/).
* **Use MJS**. MJS stands for [Module JS Loader](https://emps.ag38.ru/docs/mjs/) 
(follow this link to learn more). Although you can have the module or
mixin code in `.js` file somehere in the `htdocs` folder so that the
browser could directly load it, it is better to keep all code files
of a component in a single directory somewhere on the `modules` tree.
It will be easier to find and handle (you'll be able to copy the entire
folder to another project to enable the same component there).
Study these EMPS doc pages to get the idea of how URLs are translated
into module folder paths and how the source code of a typical EMPS
project is organized:
  * [EMPS File Structure](https://emps.ag38.ru/docs/file-structure/)
  * [URL Parsing and Variables](https://emps.ag38.ru/docs/url-variables/)
  * [Internal References in EMPS](https://emps.ag38.ru/docs/internal-references/)
  * [Module JS Loader](https://emps.ag38.ru/docs/mjs/)
* **Be aware of the `mainapp`**. Though this is not universal for
every EMPS project, in many projects the Vue application that's running
in the user's browser is referred to as the `mainapp`. Its source
code is typically at `modules/comp/mainapp.js`.
Before this Vue application is run, all the components it will need
should have been loaded and initialized, all the mixins it needs should
also have been loaded. Read the source code of `mainapp` to get the 
idea of how it works. You will notice that it heavily uses the `EMPS` JS
object - here is the source code for it: 
[https://github.com/AlexGnatko/EMPS6/blob/main/6.X/js/emps.js](https://github.com/AlexGnatko/EMPS6/blob/main/6.X/js/emps.js)
* **Do not create a new component for everything**. You might have 
picked a bad habit from a framework like Nuxt where components are easy
to create as SFCs, and you might be tempted to create a separate component
for any part of the page, for every row of a table, etc. This is not
a good approach in an EMPS project. Use a component only if you're
confident that this component will be used in many places, such as
value picker, a progress indicator, a date/time picker, etc. or when it's
important to have a separate data set for every instance of this component.
Also, components are indispensable if you need to make something recursive,
like a tree explorer or something like that. 
* **Instead, prefer mixins and includes**. Some simple things such as
making JS source code files smaller by dividing them into smaller pieces,
can be done by creating mixins. A mixin is a simple JS object that
has any of the Options API parts as required by it 
(`methods`, `data`, `computed`, `mounted`, etc.). Also, some mixins
can have common functions shared by multiple components and app pages:
one prominent example is the formats mixin at `modules/comp/formats/formats.js`.
To keep the size of your Vue HTML templates smaller, leverage the power
of Smarty - use `{{include ...}}`'s.
* **To interact with the server, use axios**. Although you can use
the built-in `fetch` as well, I regard `axios` as an easier solution,
as it has a shorter promise chain - the `then` argument already contains
the object with the data. You will find a lot of examples of how to use 
`axios` right in the project's code.
* **To broadcast messages across apps and controllers, use `vuev`**. 
Vue version 3 abandoned the concept of the application-wide **event bus**,
[Breaking change: Events API](https://v3-migration.vuejs.org/breaking-changes/events-api.html).
However, I still find it very useful for the purpose of communicating between
components and broadcasting messages to multiple components, so I took
one of the suggested ways of re-implementing it. The old-fashioned
event bus object is called `vuev` (short for 'Vue events') and implements 
`$on`, `$off`, `$emit`. Any component can register a general event 
handler using `vuev.$on`
and any component or method can broadcast a message to all listeners using
`vuev.$emit`. You will find examples of that in the code.

## Organizing Components

Although a component code can be anywhere, there are a few main conventions
that I use for keeping the component code well-organized, I suggest
sticking to those conventions.

### Components as sub-folders in the `modules` tree

I usually put general components to the `modules/comp` folder. For
project-specific components, prefer a dedicated subfolder like `modules/{project}/comp`.
One component's folder can also contain another component sub-folder, especially
when this other component is a subcomponent of the first one.

### All component-related files in one sub-folder

EMPS doesn't support Vue single-file components, so the JS code and
HTML code of a component are separate files. Moreover, there can
be 2 types of HTML code - the one that goes to the actual HTML page
and serves as the container for the future initialized Vue component,
and another one - the actual Vue HTML template code. There's even more:
some components can have server-side functionality, in which case
they will also have PHP files in their component folder.
The component folders can also contain `.css` files.

### A typical case: Option A: HTML template in the code

One of the simplest Vue components is `comp/icon`.
As it is a generic component used in many EMPS projects, it's in the
`modules/comp` folder. Another peculiar thing about it is that it is so
commonly used that it has to exist on every page of the website. That's
why the code that adds this component to the page is invoked from the
footer template of the website's HTML shell:
`{{include file="db:_comp/icon"}}`.

Do not use it as the rule, most components you will create will have to 
be referenced by the individual pages, not the general website shell
template like `head` or `foot`. Do not add your narrow-use components
to the website footer.

However, what's important here is that the `{{include ...}}` clause
that references the component's initialization HTML code is
situated outside the `<div id="mainapp"></div>`. No component templates
or `<script>` tags should be within the `mainapp` div, as it is where
the Vue application will be mounted.

Here's what a typical `comp/icon/icon.nn.htm` looks like:

```
{{if !$comp_icon_included}}
    {{var comp_icon_included=true}}
    <script type="text/x-template" id="icon-component-template">
        <svg :class="get_class()" preserveAspectRatio="xMinYMin">
            <use :xlink:href="'/css/symbols.svg#' + id" x="0" y="0"></use>
        </svg>
    </script>
    {{script src="/mjs/comp-icon/icon.js" defer=1}}
{{/if}}
```

What's important here:
1. The code is wrapped in Smarty conditional statements `{{if}}{{/if}}`
as we usually don't want a single component to be included twice on the
same page.
2. The `{{var }}` syntax is not a standard Smarty feature, it's a Smarty extension.
Use the global variable name without the `$` to set a value of a global
template variable. Use the convention `comp_{{name}}_included` to avoid collisions.
3. The `<script type="text/x-template" id="icon-component-template"></script>`
is the container of the component template in HTML. The `id` should be unique.
4. The `{{script src="/mjs/comp-icon/icon.js" defer=1}}` automatically adds
the website's `CSS reset` salt to the URL for cache busting.

Here's what `comp/icon/icon.js` looks like:

```js
emps_scripts.push(function() {
    EMPS.vue_component_direct('icon', {
        template: '#icon-component-template',
        props: ['id', 'class'],
        methods: {
            get_class: function() {
                let add = "";
                if (this['class']) {
                    add = this['class'];
                }
                return "icon " + add;
            }
        }
    });
});
```

What's important here:
1. The code is wrapped into an anonymous function added to `emps_scripts`,
which postpones the execution until after the entire page is loaded.
2. [`EMPS.vue_component_direct`](https://github.com/AlexGnatko/EMPS6/blob/main/6.X/js/emps.js)
abstracts component definition between Vue 2 and Vue 3.
3. The first argument is the tag name of the component. The second is the
[Options API](https://vuejs.org/guide/introduction.html#api-styles) object.
4. The `template` property uses the `#id` selector matching the `<script type="text/x-template">` tag.

### A typical case: Option B: HTML template in a separate file

Sometimes the HTML template of a component is elaborate enough
that it doesn't make sense to include it into every HTTP response.
In this case:
1. The component's main HTML file contains an empty `<script type="text/x-template"></script>` tag.
2. There's a new file `{name}.vue` which contains the HTML code of the Vue template.
3. The JS file uses `EMPS.vue_component` (3 arguments: tag name, template URL, options object)
instead of `EMPS.vue_component_direct`.

Option B components have one extra initialization step: their `.vue` template files
are fetched with AJAX and placed inside their `<script type="text/x-template">` tags.

### Which option to choose and when

If the component will have a small HTML template, go with Option A.

If the template will be large and elaborate, target Option B. You can temporarily
use Option A during development, then switch to Option B when the component is ready.

## Conclusion

The above-mentioned are basically all the specifics about using Vue in EMPS.
All other knowledge about using Vue Options API can be sourced from the Vue documentation.
Everything should work as expected, provided that you're not trying to use
features that require a build tool (e.g. single-file components).
