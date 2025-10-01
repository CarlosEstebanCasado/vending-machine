import { getJson } from '@/core/api/httpClient';

export interface MachineSession {
  id: string;
  state: string;
  balanceCents: number;
  insertedCoins: Record<number, number>;
  selectedProductId: string | null;
  changePlan: Record<number, number> | null;
}

export interface MachineCatalogItem {
  slotCode: string;
  productId: string | null;
  productName: string | null;
  priceCents: number | null;
  availableQuantity: number;
  capacity: number;
  recommendedSlotQuantity: number;
  status: string;
  lowStock: boolean;
}

export interface MachineCoins {
  available: Record<number, number>;
  reserved: Record<number, number>;
}

export interface MachineAlerts {
  insufficientChange: boolean;
  outOfStock: string[];
}

export interface MachineState {
  machineId: string;
  timestamp: string;
  session: MachineSession | null;
  catalog: MachineCatalogItem[];
  coins: MachineCoins;
  alerts: MachineAlerts;
}

type MachineStateResponse = {
  machine_id: string;
  timestamp: string;
  session: null | {
    id: string;
    state: string;
    balance_cents: number;
    inserted_coins: Record<string, number>;
    selected_product_id: string | null;
    change_plan: Record<string, number> | null;
  };
  catalog: Array<{
    slot_code: string;
    product_id: string | null;
    product_name: string | null;
    price_cents: number | null;
    available_quantity: number;
    capacity: number;
    recommended_slot_quantity: number;
    status: string;
    low_stock: boolean;
  }>;
  coins: {
    available: Record<string, number>;
    reserved: Record<string, number>;
  };
  alerts: {
    insufficient_change: boolean;
    out_of_stock: string[];
  };
};

const toNumberRecord = (input: Record<string, number>): Record<number, number> =>
  Object.fromEntries(
    Object.entries(input).map(([key, value]) => [Number(key), value])
  );

function mapResponse(response: MachineStateResponse): MachineState {
  return {
    machineId: response.machine_id,
    timestamp: response.timestamp,
    session:
      response.session === null
        ? null
        : {
            id: response.session.id,
            state: response.session.state,
            balanceCents: response.session.balance_cents,
            insertedCoins: toNumberRecord(response.session.inserted_coins),
            selectedProductId: response.session.selected_product_id,
            changePlan:
              response.session.change_plan === null
                ? null
                : toNumberRecord(response.session.change_plan),
          },
    catalog: response.catalog.map((item) => ({
      slotCode: item.slot_code,
      productId: item.product_id,
      productName: item.product_name,
      priceCents: item.price_cents,
      availableQuantity: item.available_quantity,
      capacity: item.capacity,
      recommendedSlotQuantity: item.recommended_slot_quantity,
      status: item.status,
      lowStock: item.low_stock,
    })),
    coins: {
      available: toNumberRecord(response.coins.available),
      reserved: toNumberRecord(response.coins.reserved),
    },
    alerts: {
      insufficientChange: response.alerts.insufficient_change,
      outOfStock: response.alerts.out_of_stock,
    },
  };
}

export async function getMachineState(): Promise<MachineState> {
  const response = await getJson<MachineStateResponse>('/machine/state');
  return mapResponse(response);
}
