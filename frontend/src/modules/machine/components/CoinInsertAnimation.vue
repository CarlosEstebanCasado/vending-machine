<template>
  <div class="coin-animation" @animationend="$emit('finished', animationId)">
    {{ label }}
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'CoinInsertAnimation',
  props: {
    label: {
      type: String,
      required: true,
    },
    animationId: {
      type: Number,
      required: true,
    },
  },
  emits: ['finished'],
})
</script>

<style scoped>
.coin-animation {
  position: absolute;
  bottom: 220px;
  left: 50%;
  transform: translate(-50%, 0);
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: radial-gradient(circle at 30% 30%, #fff6d6 0%, #f9cf58 45%, #dba617 100%);
  box-shadow: 0 8px 16px rgba(217, 119, 6, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  color: #1f2937;
  border: 1px solid rgba(255, 255, 255, 0.4);
  animation: coin-flight 1.2s ease forwards;
}

@keyframes coin-flight {
  0% {
    transform: translate(-50%, 0) scale(1);
    opacity: 1;
  }
  60% {
    transform: translate(-50%, -150px) scale(0.9);
    opacity: 1;
  }
  100% {
    transform: translate(-50%, -250px) scale(0.6);
    opacity: 0;
  }
}
</style>
