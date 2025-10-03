<template>
  <button
    class="coin-button"
    type="button"
    :disabled="disabled"
    @click="emitCoin"
  >
    {{ label }}
  </button>
</template>

<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'CoinButton',
  props: {
    label: {
      type: String,
      required: true,
    },
    value: {
      type: Number,
      required: true,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['insert'],
  methods: {
    emitCoin(): void {
      this.$emit('insert', this.value)
    },
  },
})
</script>

<style scoped>
.coin-button {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  border: none;
  background: radial-gradient(circle at 30% 30%, #fff6d6 0%, #f9cf58 45%, #dba617 100%);
  color: #1f2937;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 10px 18px rgba(217, 119, 6, 0.35);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  position: relative;
}

.coin-button::after {
  content: '';
  position: absolute;
  inset: 12%;
  border-radius: 50%;
  border: 1px solid rgba(255, 255, 255, 0.6);
  opacity: 0.8;
}

.coin-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 22px rgba(217, 119, 6, 0.45);
}

.coin-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  box-shadow: none;
}
</style>
