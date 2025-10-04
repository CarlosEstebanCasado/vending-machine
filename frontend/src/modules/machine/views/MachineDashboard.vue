<template>
  <main class="machine-layout">
    <div class="machine-wrapper">
      <section class="catalog-panel">
        <div class="admin-access">
          <button
            class="admin-access__button"
            type="button"
            :aria-label="isAdminAuthenticated ? 'Open admin dashboard' : 'Admin login'"
            @click="goToAdmin"
          >
            <span class="admin-access__icon" aria-hidden="true">🔑</span>
            <span class="admin-access__label">Admin</span>
          </button>
        </div>

        <MachineHeader
          :machine-id="machineState?.machineId ?? '—'"
          :timestamp="formattedTimestamp"
          :loading="loading"
          @refresh="refresh"
        />

        <MachineProductGrid
          :products="productCards"
          :selected-slot-code="selectedSlotCode"
          @select="selectSlot"
        />

        <div class="dispense-tray">
          <button
            v-if="dispensedProduct"
            type="button"
            class="dispensed-product"
            @click="collectDispensedProduct"
          >
            <div class="dispensed-product__image">
              <img
                v-if="dispensedProduct.imageSrc"
                :src="dispensedProduct.imageSrc"
                :alt="dispensedProduct.productName"
              />
              <span v-else class="dispensed-product__fallback">{{ dispensedProduct.slotCode }}</span>
            </div>
            <span class="dispensed-product__label">{{ dispensedProduct.productName }}</span>
            <span class="dispensed-product__hint">Tap to collect</span>
          </button>
        </div>
      </section>

      <MachineControlPanel
        :selected-slot-code="selectedSlotCode"
        :entered-code="enteredCode"
        :display-name="displayName"
        :display-price="displayPriceText"
        :balance-display="balanceDisplay"
        :required-display="requiredDisplay"
        :requirement-label="requirementLabel"
        :requirement-tone="requirementTone"
        :selection-state="selectionState"
        :keypad-buttons="keypadButtons"
        :alerts="alerts"
        :error="panelError"
        :info="panelInfo"
        :loading="loading"
        :return-disabled="returnButtonDisabled"
        :purchase-disabled="purchaseDisabled"
        :dispensed-coins="dispensedCoins"
        @keypad="handleKeypadPress"
        @insert-coin="handleInsertCoin"
        @purchase="handlePurchase"
        @return-coins="handleReturnCoins"
        @collect-coin="collectReturnedCoin"
      />
    </div>
  </main>
</template>

<script lang="ts">
import { computed, defineComponent } from 'vue'
import { useRouter } from 'vue-router'
import MachineHeader from '@/modules/machine/components/MachineHeader.vue'
import MachineProductGrid from '@/modules/machine/components/MachineProductGrid.vue'
import MachineControlPanel from '@/modules/machine/components/MachineControlPanel.vue'
import { useMachineDashboardState } from '@/modules/machine/views/useMachineDashboardState'
import { useAdminAuthStore } from '@/modules/admin/store/useAdminAuthStore'

export default defineComponent({
  name: 'MachineDashboard',
  components: {
    MachineHeader,
    MachineProductGrid,
    MachineControlPanel,
  },
  setup() {
    const state = useMachineDashboardState()
    const router = useRouter()
    const adminAuthStore = useAdminAuthStore()

    adminAuthStore.initializeFromStorage()

    const isAdminAuthenticated = computed(() => adminAuthStore.isAuthenticated)

    const goToAdmin = () => {
      if (adminAuthStore.isAuthenticated) {
        void router.push({ name: 'admin.dashboard' })
      } else {
        void router.push({ name: 'admin.login', query: { redirect: '/admin/dashboard' } })
      }
    }

    return {
      ...state,
      goToAdmin,
      isAdminAuthenticated,
    }
  },
})
</script>

<style scoped>
.machine-layout {
  min-height: 100vh;
  padding: 2.5rem;
  background: #ffffff;
  display: flex;
  justify-content: center;
  align-items: center;
  box-sizing: border-box;
}

.machine-wrapper {
  width: 100%;
  max-width: 1400px;
  display: grid;
  gap: 2rem;
  grid-template-columns: minmax(0, 3fr) minmax(320px, 1fr);
  background: linear-gradient(145deg, #d7dce8, #eef1f7);
  border-radius: 28px;
  padding: 2.5rem;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.15);
}

.catalog-panel {
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
}

.admin-access {
  display: flex;
  justify-content: flex-end;
}

.admin-access__button {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border: none;
  border-radius: 999px;
  padding: 0.35rem 0.9rem;
  background: rgba(15, 23, 42, 0.12);
  color: #0f172a;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.admin-access__button:hover {
  transform: translateY(-1px);
  background: rgba(37, 99, 235, 0.14);
  box-shadow: 0 10px 22px rgba(37, 99, 235, 0.25);
}

.admin-access__icon {
  font-size: 1.1rem;
}

.admin-access__label {
  font-size: 0.95rem;
  letter-spacing: 0.01em;
}

.dispense-tray {
  height: 200px;
  border-radius: 18px;
  background: linear-gradient(180deg, #111827 0%, #1f2937 100%);
  box-shadow: inset 0 12px 28px rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  position: relative;
  box-sizing: border-box;
}

.dispensed-product {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  background: rgba(148, 163, 184, 0.18);
  border: 1px solid rgba(226, 232, 240, 0.35);
  border-radius: 16px;
  padding: 1rem 1.25rem;
  color: #f8fafc;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  backdrop-filter: blur(6px);
}

.dispensed-product:hover {
  transform: translateY(-4px);
  box-shadow: 0 14px 28px rgba(15, 23, 42, 0.35);
  border-color: rgba(226, 232, 240, 0.55);
}

.dispensed-product:focus-visible {
  outline: 2px solid #60a5fa;
  outline-offset: 4px;
}

.dispensed-product__image {
  width: 120px;
  height: 120px;
  border-radius: 14px;
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.dispensed-product__image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.dispensed-product__fallback {
  font-size: 1.4rem;
  font-weight: 700;
  color: #cbd5f5;
}

.dispensed-product__label {
  font-size: 1.1rem;
}

.dispensed-product__hint {
  font-size: 0.85rem;
  color: #cbd5f5;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

@media (max-width: 1024px) {
  .machine-wrapper {
    grid-template-columns: 1fr;
    padding: 1.75rem;
  }
}

@media (max-width: 640px) {
  .machine-layout {
    padding: 1.5rem 1rem;
  }
}
</style>
