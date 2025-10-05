<template>
  <section class="inventory">
    <header class="inventory__header">
      <div>
        <h2>Product Inventory</h2>
        <p>Review slot availability and trigger restock or withdrawals.</p>
      </div>
      <button
        class="inventory__refresh"
        type="button"
        :disabled="store.loading"
        @click="refresh"
      >
        {{ store.loading ? 'Refreshing...' : 'Refresh' }}
      </button>
    </header>

    <div v-if="store.loading" class="inventory__feedback">
      Loading slot inventory…
    </div>
    <div v-else-if="store.error" class="inventory__feedback inventory__feedback--error">
      {{ store.error }}
    </div>
    <div v-else class="inventory__content">
      <aside class="inventory__filters">
        <div class="inventory__stats">
          <span>{{ store.slots.length }} slots</span>
          <span>{{ store.lowStockCount }} low stock</span>
          <span>{{ store.disabledCount }} disabled</span>
        </div>

        <label class="inventory__filter">
          <span>Status</span>
          <select v-model="statusFilter">
            <option value="all">All statuses</option>
            <option value="available">Available</option>
            <option value="reserved">Reserved</option>
            <option value="disabled">Disabled</option>
          </select>
        </label>

        <label class="inventory__filter inventory__filter--checkbox">
          <input type="checkbox" v-model="needsRestockOnly" />
          Show only low stock slots
        </label>

        <label class="inventory__filter">
          <span>Search</span>
          <input
            v-model.trim="searchTerm"
            type="search"
            placeholder="Search by code or product"
          />
        </label>

        <ul class="inventory__slot-list">
          <li
            v-for="slot in slots"
            :key="slot.code"
            :class="['inventory__slot', { 'inventory__slot--active': slot.code === store.selectedSlotCode }]"
          >
            <button type="button" @click="store.selectSlot(slot.code)">
              <div class="inventory__slot-line">
                <span class="inventory__slot-code">{{ slot.code }}</span>
                <span
                  class="inventory__slot-status"
                  :class="statusClass(slot)"
                >
                  {{ statusLabel(slot.status) }}
                </span>
              </div>
              <div class="inventory__slot-line inventory__slot-line--secondary">
                <span
                  class="inventory__slot-product"
                  :title="slot.productName ?? 'Unassigned'"
                >
                  {{ slot.productName ?? 'Unassigned' }}
                </span>
                <span class="inventory__slot-quantity" :title="`${slot.quantity} / ${slot.capacity}`">
                  {{ slot.quantity }}/{{ slot.capacity }}
                </span>
              </div>
            </button>
          </li>
        </ul>

        <p v-if="slots.length === 0" class="inventory__empty">No slots match the current filters.</p>
      </aside>

      <section class="inventory__details" v-if="selectedSlot">
        <header class="inventory__details-header">
          <div>
            <h3>Slot {{ selectedSlot.code }}</h3>
            <p>{{ statusLabel(selectedSlot.status) }} • {{ selectedSlot.quantity }}/{{ selectedSlot.capacity }} items</p>
          </div>
          <span
            class="inventory__details-pill"
            :class="selectedSlot.needsRestock ? 'inventory__details-pill--alert' : 'inventory__details-pill--ok'"
          >
            {{ selectedSlot.needsRestock ? 'Needs restock' : 'Stock healthy' }}
          </span>
        </header>

        <dl class="inventory__details-grid">
          <div>
            <dt>Restock threshold</dt>
            <dd>{{ selectedSlot.restockThreshold }}</dd>
          </div>
          <div>
            <dt>Recommended quantity</dt>
            <dd>{{ selectedSlot.recommendedSlotQuantity ?? '—' }}</dd>
          </div>
          <div>
            <dt>Product</dt>
            <dd>{{ selectedSlot.productName ?? 'None assigned' }}</dd>
          </div>
          <div>
            <dt>Price</dt>
            <dd>{{ selectedSlot.priceCents != null ? formatCurrency(selectedSlot.priceCents) : '—' }}</dd>
          </div>
        </dl>

        <section class="inventory__action">
          <header>
            <h4>Adjust Slot Inventory</h4>
            <p>Record restocks or withdrawals for this slot.</p>
          </header>

          <form class="inventory__form" @submit.prevent="submitAdjustment">
            <fieldset class="inventory__operation" :disabled="store.submitting">
              <legend class="visually-hidden">Select operation</legend>
              <label :class="['inventory__operation-option', { 'inventory__operation-option--active': operation === 'restock' } ]">
                <input
                  v-model="operation"
                  type="radio"
                  name="inventory-operation"
                  value="restock"
                  :disabled="store.submitting"
                />
                <span>Restock</span>
              </label>
              <label :class="['inventory__operation-option', { 'inventory__operation-option--active': operation === 'withdraw' } ]">
                <input
                  v-model="operation"
                  type="radio"
                  name="inventory-operation"
                  value="withdraw"
                  :disabled="store.submitting"
                />
                <span>Withdraw</span>
              </label>
            </fieldset>

            <label class="inventory__form-field">
              <span>Quantity</span>
              <input
                v-model.number="quantity"
                type="number"
                min="0"
                step="1"
                inputmode="numeric"
                placeholder="0"
                :disabled="store.submitting"
              />
            </label>

            <label
              v-if="operation === 'restock' && productSelectionVisible"
              class="inventory__form-field"
            >
              <span>Product</span>
              <select v-model="selectedProductId" :disabled="store.submitting">
                <option value="" disabled>Select product</option>
                <option
                  v-for="product in productOptions"
                  :key="product.id"
                  :value="product.id"
                >
                  {{ product.name }}
                  <span v-if="product.priceCents != null"> — {{ formatCurrency(product.priceCents) }}</span>
                </option>
              </select>
            </label>

            <p v-if="store.submitError" class="inventory__feedback inventory__feedback--error">
              {{ store.submitError }}
            </p>
            <p v-else-if="store.submitSuccess" class="inventory__feedback inventory__feedback--success">
              {{ store.submitSuccess }}
            </p>

            <button class="inventory__submit" type="submit" :disabled="store.submitting">
              {{ store.submitting ? 'Saving...' : submitLabel }}
            </button>
          </form>
        </section>
      </section>
      <section v-else class="inventory__details inventory__details--empty">
        <p>Select a slot to view details.</p>
      </section>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import type { AdminSlotInventoryItem } from '@/modules/admin/api/getSlotInventory'
import { useAdminInventoryStore } from '@/modules/admin/store/useAdminInventoryStore'

const store = useAdminInventoryStore()
const operation = ref<'restock' | 'withdraw'>('restock')
const quantity = ref<number>(0)
const selectedProductId = ref<string>('')

onMounted(() => {
  if (store.slots.length === 0) {
    void store.fetchSlots()
  }
})

const slots = computed(() => store.filteredSlots)
const selectedSlot = computed(() => store.selectedSlot)
const productOptions = computed(() => store.productOptions)
const submitLabel = computed(() => (operation.value === 'restock' ? 'Record Restock' : 'Record Withdrawal'))

const statusFilter = computed({
  get: () => store.filters.status,
  set: (value: typeof store.filters.status) => store.setStatusFilter(value),
})

const needsRestockOnly = computed({
  get: () => store.filters.needsRestockOnly,
  set: (value: boolean) => store.setNeedsRestockOnly(value),
})

const searchTerm = computed({
  get: () => store.filters.search,
  set: (value: string) => store.setSearch(value),
})

const productSelectionVisible = computed(() => {
  if (operation.value === 'withdraw') {
    return false
  }

  const slot = selectedSlot.value

  if (!slot) {
    return false
  }

  const slotReadyForAssignment = slot.quantity === 0 && slot.productId === null

  return slotReadyForAssignment && productOptions.value.length > 0
})

watch(selectedSlot, (slot) => {
  quantity.value = 0
  operation.value = 'restock'
  selectedProductId.value = slot?.productId ?? ''
  store.resetFeedback()
})

watch(operation, () => {
  store.resetFeedback()
})

watch(productSelectionVisible, (visible) => {
  if (!visible) {
    selectedProductId.value = ''
    return
  }

  if (!selectedProductId.value && productOptions.value.length > 0) {
    selectedProductId.value = productOptions.value[0].id
  }
})

function statusLabel(status: AdminSlotInventoryItem['status']): string {
  switch (status) {
    case 'available':
      return 'Available'
    case 'reserved':
      return 'Reserved'
    case 'disabled':
      return 'Disabled'
  }
}

function statusClass(slot: AdminSlotInventoryItem): string {
  if (slot.status === 'disabled') {
    return 'inventory__slot-status--disabled'
  }

  if (slot.needsRestock) {
    return 'inventory__slot-status--warning'
  }

  return 'inventory__slot-status--ok'
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
  }).format(value / 100)
}

async function refresh(): Promise<void> {
  await store.fetchSlots()
}

async function submitAdjustment(): Promise<void> {
  if (!selectedSlot.value) {
    return
  }

  const normalizedQuantity = Number.isFinite(quantity.value) ? Math.max(0, Math.floor(quantity.value)) : 0
  quantity.value = normalizedQuantity

  if (operation.value === 'withdraw' && normalizedQuantity > selectedSlot.value.quantity) {
    store.submitError = `Cannot withdraw more than available stock (${selectedSlot.value.quantity}).`
    return
  }

  let productId: string | null | undefined = selectedSlot.value.productId

  if (operation.value === 'restock') {
    if (!productId) {
      productId = selectedProductId.value || undefined
    }

    if (!productId) {
      store.submitError = 'Select a product to assign to this slot.'
      return
    }
  }

  await store.adjustSlot(operation.value, selectedSlot.value.code, normalizedQuantity, productId)

  if (!store.submitError) {
    quantity.value = 0
    if (operation.value === 'restock' && selectedSlot.value?.productId) {
      selectedProductId.value = selectedSlot.value.productId
    }
  }
}
</script>

<style scoped>
.inventory {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.inventory__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.inventory__header h2 {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 700;
}

.inventory__header p {
  margin: 0.35rem 0 0;
  color: #cbd5f5;
}


.inventory__refresh {
  border: none;
  background: rgba(51, 65, 85, 0.9);
  color: #f8fafc;
  border-radius: 999px;
  padding: 0.5rem 1.2rem;
  font-weight: 600;
  border: 1px solid rgba(148, 163, 184, 0.3);
  cursor: pointer;
  transition: background 0.2s ease-in-out;
}

.inventory__refresh:hover {
  background: rgba(71, 85, 105, 0.95);
}

.inventory__refresh:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.inventory__feedback {
  background: rgba(12, 20, 35, 0.95);
  border-radius: 18px;
  padding: 1.5rem;
  text-align: center;
  color: #cbd5f5;
  border: 1px solid rgba(148, 163, 184, 0.2);
}

.inventory__feedback--error {
  color: #fecaca;
}

.inventory__feedback--success {
  color: #bbf7d0;
}

.inventory__content {
  display: grid;
  grid-template-columns: minmax(260px, 320px) 1fr;
  gap: 1.5rem;
}

.inventory__filters {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  background: rgba(12, 20, 35, 0.95);
  border-radius: 18px;
  padding: 1.25rem;
  border: 1px solid rgba(148, 163, 184, 0.2);
  box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.08);
}

.inventory__stats {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem 0.75rem;
  font-size: 0.85rem;
  color: #cbd5f5;
}

.inventory__filter {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  font-size: 0.9rem;
  color: #cbd5f5;
}

.inventory__filter select,
.inventory__filter input[type="search"] {
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.3);
  background: rgba(15, 23, 42, 0.85);
  padding: 0.55rem 0.9rem;
  color: #f8fafc;
}

.inventory__filter select:focus,
.inventory__filter input:focus {
  outline: none;
  border-color: #818cf8;
  box-shadow: 0 0 0 2px rgba(129, 140, 248, 0.35);
}

.inventory__filter--checkbox {
  flex-direction: row;
  align-items: center;
  gap: 0.6rem;
}

.inventory__slot-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-height: 24rem;
  overflow-y: auto;
  padding-right: 0.25rem;
}

.inventory__slot {
  border-radius: 16px;
  background: rgba(15, 23, 42, 0.85);
  border: 1px solid transparent;
}

.inventory__slot--active {
  border-color: #818cf8;
  box-shadow: 0 0 0 2px rgba(129, 140, 248, 0.35);
}

.inventory__slot button {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  background: transparent;
  border: none;
  color: inherit;
  padding: 0.9rem;
  text-align: left;
  cursor: pointer;
}

.inventory__slot-line {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: space-between;
}

.inventory__slot-line--secondary {
  font-size: 0.85rem;
  color: #cbd5f5;
  align-items: flex-start;
}

.inventory__slot-code {
  font-weight: 700;
}

.inventory__slot-product {
  flex: 1;
  min-width: 0;
  overflow: visible;
  white-space: normal;
}

.inventory__slot-quantity {
  margin-left: 1rem;
  white-space: nowrap;
}

.inventory__slot-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 0.15rem 0.6rem;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #0f172a;
  margin-left: auto;
}

.inventory__slot-status--ok {
  background: #bbf7d0;
}

.inventory__slot-status--warning {
  background: #fde68a;
}

.inventory__slot-status--disabled {
  background: #fca5a5;
}

.inventory__empty {
  margin: 0;
  font-size: 0.85rem;
  color: #cbd5f5;
}

.inventory__details {
  background: rgba(12, 20, 35, 0.95);
  border-radius: 18px;
  border: 1px solid rgba(148, 163, 184, 0.2);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  min-height: 24rem;
}

.inventory__details--empty {
  align-items: center;
  justify-content: center;
}

.inventory__details-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.inventory__details-header h3 {
  margin: 0;
  font-size: 1.2rem;
}

.inventory__details-header p {
  margin: 0.35rem 0 0;
  color: #cbd5f5;
}

.inventory__details-pill {
  border-radius: 999px;
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.inventory__details-pill--alert {
  background: rgba(239, 68, 68, 0.2);
  color: #fca5a5;
}

.inventory__details-pill--ok {
  background: rgba(34, 197, 94, 0.2);
  color: #bbf7d0;
}

.inventory__details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 1rem;
}

.inventory__details-grid dt {
  font-size: 0.8rem;
  color: #94a3b8;
  margin-bottom: 0.25rem;
}

.inventory__details-grid dd {
  margin: 0;
  font-size: 0.95rem;
}

.inventory__action header {
  margin-bottom: 0.75rem;
}

.inventory__action h4 {
  margin: 0;
  font-size: 1.05rem;
}

.inventory__action p {
  margin: 0.35rem 0 0;
  color: #cbd5f5;
}

.inventory__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.inventory__operation {
  display: inline-flex;
  gap: 0.4rem;
  border: none;
  padding: 0.05rem 0;
  margin: 0;
}

.inventory__operation-option {
  flex: 0 0 auto;
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.3);
  background: rgba(15, 23, 42, 0.85);
  color: #f8fafc;
  padding: 0.28rem 0.85rem;
  display: inline-flex;
  justify-content: center;
  align-items: center;
  font-size: 0.8rem;
  gap: 0.3rem;
  min-width: 6rem;
  cursor: pointer;
}

.inventory__operation-option input {
  pointer-events: none;
}

.inventory__operation-option--active {
  border-color: #818cf8;
  background: rgba(129, 140, 248, 0.25);
}

.inventory__form-field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  font-size: 0.9rem;
  color: #cbd5f5;
}

.inventory__form-field input,
.inventory__form-field select {
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.3);
  background: rgba(15, 23, 42, 0.85);
  padding: 0.55rem 0.9rem;
  color: #f8fafc;
}

.inventory__form-field input:focus,
.inventory__form-field select:focus {
  outline: none;
  border-color: #818cf8;
  box-shadow: 0 0 0 2px rgba(129, 140, 248, 0.35);
}

.inventory__submit {
  align-self: flex-start;
  border: none;
  background: #818cf8;
  color: #0f172a;
  font-weight: 600;
  border-radius: 999px;
  padding: 0.65rem 1.6rem;
  transition: background 0.2s ease-in-out;
  cursor: pointer;
}

.inventory__submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 960px) {
  .inventory__content {
    grid-template-columns: 1fr;
  }

  .inventory__slot button {
    grid-template-columns: 0.6fr 0.9fr auto;
  }

  .inventory__details {
    min-height: auto;
  }
}
</style>
