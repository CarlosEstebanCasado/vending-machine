<template>
  <div class="product-grid">
    <article
      v-for="item in sortedProducts"
      :key="item.slotCode"
      class="product-card"
      :class="{
        'product-card--selected': item.slotCode === selectedSlotCode,
        'product-card--empty': !item.productId,
      }"
      @click="$emit('select', item.slotCode)"
    >
      <div class="product-card__image">
        <img v-if="imageSrc(item)" :src="imageSrc(item)" :alt="item.productName ?? 'Empty slot'" />
      </div>
      <div class="product-card__info">
        <span class="product-card__slot">{{ item.slotCode }}</span>
        <div class="product-card__details">
          <span class="product-card__name">{{ item.productName ?? 'Empty' }}</span>
          <span class="product-card__price">
            {{ item.priceCents !== null ? centsToCurrency(item.priceCents) : '—' }}
          </span>
        </div>
        <span class="product-card__quantity">×{{ item.availableQuantity }}</span>
      </div>
    </article>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import type { MachineCatalogItem } from '@/modules/machine/api/getMachineState'

export default defineComponent({
  name: 'MachineProductGrid',
  props: {
    products: {
      type: Array as PropType<MachineCatalogItem[]>,
      required: true,
    },
    selectedSlotCode: {
      type: String,
      default: '',
    },
  },
  emits: ['select'],
  computed: {
    sortedProducts(): MachineCatalogItem[] {
      return [...this.products].sort((a, b) => Number(a.slotCode) - Number(b.slotCode))
    },
  },
  methods: {
    centsToCurrency(cents: number): string {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
      }).format(cents / 100)
    },
    imageSrc(item: MachineCatalogItem): string | undefined {
      if (!item.productName) {
        return undefined
      }

      const map: Record<string, string> = {
        Water: '/watter.png',
        Soda: '/soda.png',
        'Orange Juice': '/orange-juice.png',
      }

      return map[item.productName]
    },
  },
})
</script>

<style scoped>
.product-grid {
  display: grid;
  gap: 1.25rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.product-card {
  background: linear-gradient(160deg, #f8fafc 0%, #e7ecf5 100%);
  border: 1px solid rgba(148, 163, 184, 0.3);
  border-radius: 18px;
  padding: 1.25rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  box-shadow: 0 12px 16px rgba(15, 23, 42, 0.08);
}

.product-card--selected {
  border-color: #2563eb;
  box-shadow: 0 14px 25px rgba(37, 99, 235, 0.22);
  transform: translateY(-2px);
}

.product-card__image {
  height: 140px;
  border-radius: 16px;
  background: linear-gradient(145deg, #cbd5f5 0%, #94a3ff 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.product-card__image img {
  width: 90%;
  height: auto;
  object-fit: contain;
}

.product-card--empty .product-card__image {
  background: repeating-linear-gradient(135deg, #e2e8f0, #e2e8f0 12px, #cbd5e1 12px, #cbd5e1 24px);
}

.product-card__info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.product-card__slot {
  font-weight: 700;
  font-size: 1.1rem;
  color: #1e293b;
}

.product-card__details {
  display: flex;
  justify-content: space-between;
  color: #475569;
  font-weight: 600;
}

.product-card__price {
  color: #1e293b;
}

.product-card__quantity {
  font-size: 0.9rem;
  font-weight: 600;
  color: #2563eb;
  background: rgba(37, 99, 235, 0.12);
  border-radius: 999px;
  align-self: flex-start;
  padding: 0.25rem 0.6rem;
}

@media (max-width: 640px) {
  .product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 480px) {
  .product-grid {
    grid-template-columns: 1fr;
  }
}
</style>
