export const PRODUCT_IMAGE_MAP: Record<string, string> = {
  Water: '/watter.png',
  Soda: '/soda.png',
  'Orange Juice': '/orange-juice.png',
}

export function getProductImage(productName?: string | null): string | undefined {
  if (!productName) {
    return undefined
  }

  return PRODUCT_IMAGE_MAP[productName] ?? undefined
}
