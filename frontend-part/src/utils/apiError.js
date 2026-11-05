// يحوّل أي خطأ قادم من axios إلى شكل موحّد وسهل العرض.
// الباك بيرجّع الأخطاء بالشكل: { error: { code, message } }
// وأخطاء الفاليديشن (422) غالباً: { errors: { field: [msg] } } أو { error: { details: {...} } }
export function parseApiError(error) {
  const data = error?.response?.data
  const status = error?.response?.status

  const message =
    data?.error?.message ||
    data?.message ||
    error?.message ||
    'حدث خطأ غير متوقع'

  const raw = data?.errors || data?.error?.errors || data?.error?.details
  const fields = {}
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    for (const [key, value] of Object.entries(raw)) {
      fields[key] = Array.isArray(value) ? value[0] : String(value)
    }
  }

  return { status, code: data?.error?.code || null, message, fields }
}
