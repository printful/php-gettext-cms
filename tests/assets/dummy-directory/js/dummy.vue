<template>
    {{pgettext('ctx', 'JS 1')}}
</template>

<script>
    export default {
        computed: {
            method() {
                return this.gettext('JS 2');
            }
        }
    }
</script>