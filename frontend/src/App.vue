<script lang="ts">
import { defineComponent } from 'vue'
import { RouterView } from 'vue-router'

export default defineComponent({
  name: 'App',
  components: {
    RouterView,
  },
})
</script>

<template>
  <RouterView />
</template>

<style>
:root {
  color-scheme: light;
  font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background-color: #f8fafc;
  color: #0f172a;
}

a {
  color: inherit;
}

body {
  margin: 0;
  min-height: 100vh;
}
</style>
