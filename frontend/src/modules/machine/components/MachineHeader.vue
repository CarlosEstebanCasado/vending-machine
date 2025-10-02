<template>
  <header class="panel-header">
    <div>
      <h1>Vending Machine</h1>
      <p class="panel-subtitle">A fully simulated experience</p>
    </div>
    <div class="panel-meta">
      <span class="meta-item">Machine ID: {{ machineId }}</span>
      <span class="meta-item">Last update: {{ timestamp }}</span>
    </div>
    <button type="button" class="refresh" @click="$emit('refresh')" :disabled="loading">
      {{ loading ? 'Refreshing…' : 'Refresh' }}
    </button>
  </header>
</template>

<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'MachineHeader',
  props: {
    machineId: {
      type: String,
      required: true,
    },
    timestamp: {
      type: String,
      required: true,
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['refresh'],
})
</script>

<style scoped>
.panel-header {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  align-items: center;
}

.panel-header h1 {
  margin: 0;
  font-size: 2rem;
}

.panel-subtitle {
  margin: 0.25rem 0 0;
  color: #475569;
  font-size: 0.95rem;
}

.panel-meta {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  color: #475569;
}

.meta-item {
  font-size: 0.85rem;
}

.refresh {
  justify-self: end;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 12px;
  padding: 0.65rem 1.2rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.refresh:disabled {
  opacity: 0.6;
  cursor: wait;
  box-shadow: none;
}

.refresh:not(:disabled):hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 24px rgba(37, 99, 235, 0.35);
}

@media (max-width: 640px) {
  .panel-header {
    grid-template-columns: 1fr;
  }

  .refresh {
    justify-self: stretch;
  }
}
</style>
