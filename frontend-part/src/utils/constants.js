export const ORDER_STATUSES = {
  PENDING: 'قيد الانتظار',
  CONFIRMED: 'مؤكد',
  SHIPPED: 'تم الشحن',
  DELIVERED: 'تم التسليم',
  CANCELLED: 'ملغي',
}
export const STATUS_FLOW = ['PENDING', 'CONFIRMED', 'SHIPPED', 'DELIVERED']
export const CANCELLABLE_STATUSES = ['PENDING', 'CONFIRMED']
export const BASE_CATEGORIES = ['Electronics', 'Clothing', 'Books', 'Home']

const CATEGORY_LABELS = { Electronics: 'إلكترونيات', Clothing: 'ملابس', Books: 'كتب', Home: 'المنزل' }
export const categoryLabel = (value) => CATEGORY_LABELS[value] || value
