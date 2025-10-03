<template>
  <aside class="control-panel">
    <div class="product-display">
      <div class="product-display__header">
        <span class="product-display__slot">Slot {{ selectedSlotCode || '—' }}</span>
        <span v-if="enteredCode" class="product-display__entered">{{ enteredCode }}</span>
      </div>

      <h2 class="product-display__name">{{ displayName }}</h2>
      <div class="product-display__price">{{ displayPrice }}</div>

      <dl class="product-display__status">
        <div>
          <dt>Inserted coins</dt>
          <dd>{{ balanceDisplay }}</dd>
        </div>
        <div>
          <dt>{{ requirementLabel }}</dt>
          <dd :class="requirementClasses">{{ requiredDisplay }}</dd>
        </div>
      </dl>

      <div v-if="selectionState === 'idle'" class="product-display__marquee">
        <div class="marquee-track">
          <span>Select a product to start the sale · </span>
          <span>Select a product to start the sale · </span>
          <span>Select a product to start the sale · </span>
        </div>
      </div>

      <div v-else-if="selectionState === 'unavailable'" class="product-display__warning">
        <span class="warning-icon">!</span>
        <p>Product unavailable</p>
      </div>
    </div>

    <div class="keypad">
      <div v-for="row in keypadButtons" :key="row.join('-')" class="keypad-row">
        <button
          v-for="key in row"
          :key="key"
          class="keypad-key"
          type="button"
          :disabled="loading"
          @click="$emit('keypad', key)"
        >
          {{ key }}
        </button>
      </div>
    </div>

    <div class="coin-controls">
      <CoinButton
        v-for="coin in coins"
        :key="coin.value"
        :label="coin.label"
        :value="coin.value"
        :disabled="loading"
        @insert="handleCoinButton"
      />
    </div>

    <div class="coin-animations">
      <CoinInsertAnimation
        v-for="animation in coinAnimations"
        :key="animation.id"
        :label="animation.label"
        :animation-id="animation.id"
        @finished="removeCoinAnimation"
      />
    </div>

    <div class="coin-insert"></div>

    <div class="actions">
      <button class="action primary" type="button" disabled>Buy product</button>
      <button
        class="action secondary"
        type="button"
        :disabled="returnDisabled"
        @click="$emit('return-coins')"
      >
        Return coins
      </button>
    </div>

    <div class="coin-slot">
      <button
        v-for="coin in dispensedCoins"
        :key="coin.id"
        class="coin-slot__coin"
        type="button"
        @click="$emit('collect-coin', coin.id)"
      >
        {{ coin.label }}
      </button>
    </div>

    <p v-if="error" class="inline-alert error">{{ error }}</p>
    <p v-else-if="info" class="inline-alert info">{{ info }}</p>
    <p v-else-if="alerts.outOfStock.length" class="inline-alert warning">
      Out of stock: {{ alerts.outOfStock.join(', ') }}
    </p>
  </aside>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import type { MachineAlerts } from '@/modules/machine/api/getMachineState'
import CoinButton from '@/modules/machine/components/CoinButton.vue'
import CoinInsertAnimation from '@/modules/machine/components/CoinInsertAnimation.vue'

type CoinDefinition = {
  label: string
  value: number
}

type CoinAnimation = CoinDefinition & { id: number }

export type DispensedCoin = {
  id: number
  label: string
  value: number
}

const AVAILABLE_COINS: CoinDefinition[] = [
  { label: '€1.00', value: 100 },
  { label: '€0.25', value: 25 },
  { label: '€0.10', value: 10 },
  { label: '€0.05', value: 5 },
]

export default defineComponent({
  name: 'MachineControlPanel',
  components: {
    CoinButton,
    CoinInsertAnimation,
  },
  props: {
    selectedSlotCode: {
      type: String,
      default: '',
    },
    enteredCode: {
      type: String,
      default: '',
    },
    displayName: {
      type: String,
      required: true,
    },
    displayPrice: {
      type: String,
      required: true,
    },
    balanceDisplay: {
      type: String,
      required: true,
    },
    requiredDisplay: {
      type: String,
      required: true,
    },
    requirementLabel: {
      type: String,
      default: 'Required',
    },
    requirementTone: {
      type: String as () => 'neutral' | 'warning' | 'positive',
      default: 'neutral',
    },
    selectionState: {
      type: String as () => 'idle' | 'ready' | 'unavailable',
      required: true,
    },
    keypadButtons: {
      type: Array as PropType<string[][]>,
      required: true,
    },
    alerts: {
      type: Object as PropType<MachineAlerts>,
      required: true,
    },
    error: {
      type: String as PropType<string | null>,
      default: null,
    },
    info: {
      type: String as PropType<string | null>,
      default: null,
    },
    loading: {
      type: Boolean,
      default: false,
    },
    returnDisabled: {
      type: Boolean,
      default: false,
    },
    dispensedCoins: {
      type: Array as PropType<DispensedCoin[]>,
      default: () => [],
    },
  },
  emits: ['keypad', 'insert-coin', 'return-coins', 'collect-coin'],
  data() {
    return {
      coins: AVAILABLE_COINS,
      coinAnimations: [] as CoinAnimation[],
    }
  },
  computed: {
    requirementClasses(): Record<string, boolean> {
      return {
        'product-display__status-value': true,
        'product-display__status-value--warning': this.requirementTone === 'warning',
        'product-display__status-value--positive': this.requirementTone === 'positive',
      }
    },
  },
  methods: {
    handleCoinButton(value: number): void {
      if (this.loading) {
        return
      }

      const coin = this.findCoinByValue(value)
      if (!coin) {
        return
      }

      this.startCoinAnimation(coin)
      this.$emit('insert-coin', coin.value)
    },
    removeCoinAnimation(id: number): void {
      this.coinAnimations = this.coinAnimations.filter((animation) => animation.id !== id)
    },
    findCoinByValue(value: number): CoinDefinition | undefined {
      return this.coins.find((item) => item.value === value)
    },
    startCoinAnimation(coin: CoinDefinition): void {
      const animation: CoinAnimation = {
        ...coin,
        id: Date.now() + Math.floor(Math.random() * 1000),
      }

      this.coinAnimations.push(animation)
    },
  },
})
</script>

<style scoped>
.control-panel {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  background: linear-gradient(155deg, #0f172a 0%, #1e293b 60%, #0b1220 100%);
  border-radius: 24px;
  padding: 1.75rem;
  color: white;
  box-shadow: 0 18px 35px rgba(15, 23, 42, 0.45);
  position: relative;
}

.product-display {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  background: rgba(15, 23, 42, 0.6);
  border-radius: 18px;
  padding: 1.5rem;
  border: 1px solid rgba(148, 163, 184, 0.2);
  position: relative;
  overflow: hidden;
  min-height: 240px;
}

.product-display__header {
  display: flex;
  justify-content: space-between;
  font-size: 0.9rem;
  color: #cbd5f5;
}

.product-display__name {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 600;
}

.product-display__price {
  font-size: 2rem;
  font-weight: 700;
  color: #60a5fa;
}

.product-display__status {
  margin: 0;
  display: grid;
  gap: 0.5rem;
}

.product-display__status div {
  display: flex;
  justify-content: space-between;
  color: #cbd5f5;
}

.product-display__status dd {
  margin: 0;
  font-weight: 600;
  color: #f8fafc;
}

.product-display__status-value {
  color: #f8fafc;
}

.product-display__status-value--warning {
  color: #f87171;
}

.product-display__status-value--positive {
  color: #4ade80;
}

.product-display__marquee {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  padding: 0 1.5rem;
  background: rgba(15, 23, 42, 0.82);
  pointer-events: none;
}

.product-display__warning {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.85rem;
  border-radius: 16px;
  background: rgba(30, 41, 59, 0.85);
  border: 1px solid rgba(251, 191, 36, 0.35);
  color: #fde68a;
  text-align: center;
  font-weight: 600;
  padding: 1.25rem;
  box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.4);
  pointer-events: none;
}

.product-display__warning p {
  margin: 0;
}

.warning-icon {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  background: linear-gradient(180deg, #f97316 0%, #fb923c 100%);
  color: #1f2937;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  box-shadow: 0 8px 18px rgba(249, 115, 22, 0.35);
}

.marquee-track {
  display: inline-flex;
  animation: marquee 12s linear infinite;
  white-space: nowrap;
  color: #cbd5f5;
  font-weight: 600;
  font-size: 0.95rem;
}

.marquee-track span {
  padding-right: 1.5rem;
}

@keyframes marquee {
  0% {
    transform: translateX(0);
  }
  100% {
    transform: translateX(-50%);
  }
}

.keypad {
  display: grid;
  gap: 0.75rem;
}

.keypad-row {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(3, 1fr);
}

.keypad-key {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 52px;
  border: none;
  border-radius: 12px;
  background: rgba(148, 163, 184, 0.12);
  border: 1px solid rgba(148, 163, 184, 0.25);
  font-size: 1.1rem;
  color: #e2e8f0;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.keypad-key:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 14px rgba(15, 23, 42, 0.2);
}

.keypad-key:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}


.coin-controls {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.75rem;
}

.coin-insert {
  height: 100px;
  border-radius: 18px;
  background: linear-gradient(180deg, #e2e8f0 0%, #cbd5e1 100%);
  box-shadow: inset 0 12px 24px rgba(148, 163, 184, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
}

.coin-insert::before {
  content: '';
  width: 60px;
  height: 14px;
  border-radius: 999px;
  background: linear-gradient(180deg, #0f172a 0%, #1f2937 100%);
  box-shadow: inset 0 3px 8px rgba(15, 23, 42, 0.45);
}

.actions {
  display: grid;
  gap: 0.75rem;
}

.action {
  border: none;
  border-radius: 14px;
  padding: 0.75rem 1.25rem;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
}

.action.primary {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: white;
}

.action.secondary {
  background: rgba(148, 163, 184, 0.2);
  color: #cbd5f5;
}

.action[disabled] {
  cursor: not-allowed;
  opacity: 0.6;
}

.coin-slot {
  min-height: 160px;
  border-radius: 18px;
  background: linear-gradient(180deg, #111827 0%, #050a13 100%);
  box-shadow: inset 0 18px 32px rgba(0, 0, 0, 0.45);
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
  padding: 1rem;
}

.coin-slot__coin {
  background: linear-gradient(160deg, #fbbf24 0%, #f97316 100%);
  border: none;
  border-radius: 999px;
  padding: 0.4rem 0.9rem;
  color: #0f172a;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 8px 16px rgba(249, 115, 22, 0.35);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.coin-slot__coin:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 20px rgba(249, 115, 22, 0.45);
}

.coin-animations {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.inline-alert {
  margin: 0;
  padding: 0.75rem 1rem;
  border-radius: 12px;
  font-size: 0.9rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.inline-alert.error {
  background: rgba(248, 113, 113, 0.15);
  color: #fecaca;
}

.inline-alert.warning {
  background: rgba(251, 191, 36, 0.15);
  color: #fde68a;
}

.inline-alert.info {
  background: rgba(129, 140, 248, 0.15);
  border-color: rgba(129, 140, 248, 0.35);
  color: #cdd5ff;
}

@media (max-width: 1024px) {
  .control-panel {
    order: -1;
  }
}
</style>
