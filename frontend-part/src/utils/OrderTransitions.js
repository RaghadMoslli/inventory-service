// نفس جدول الباك إند (OrderDecisionService::TRANSITIONS). الباك إند هو المرجع، وهذا للعرض فقط.
export const ALLOWED_TRANSITIONS = {
  PENDING: ['CONFIRMED', 'CANCELLED'],
  CONFIRMED: ['SHIPPED', 'CANCELLED'],
  SHIPPED: ['DELIVERED'],
  DELIVERED: [],
  CANCELLED: [],
}

// الحالات التي يقدر الأدمن ينقل لها من الحالة الحالية (للقائمة المنسدلة)
export const nextStatuses = (current) => ALLOWED_TRANSITIONS[current] ?? []

// العميل يلغي فقط من PENDING أو CONFIRMED (لإظهار زر الإلغاء)
export const canCustomerCancel = (status) => status === 'PENDING' || status === 'CONFIRMED'