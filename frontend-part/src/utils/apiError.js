const VALIDATION_PATTERNS = [
  [/has already been taken/i, () => 'هذه القيمة مستخدمة مسبقاً'],
  [/field is required/i, () => 'هذا الحقل مطلوب'],
  [/must be a valid email/i, () => 'صيغة البريد الإلكتروني غير صحيحة'],
  [/must be at least (\d+) characters/i, (m) => `يجب ألا يقل طوله عن ${m[1]} أحرف`],
  [/(?:may not|must not) be greater than (\d+) characters/i, (m) => `يجب ألا يتجاوز ${m[1]} حرفاً`],
  [/must be a number/i, () => 'يجب أن تكون القيمة رقماً'],
  [/must be an integer/i, () => 'يجب أن تكون القيمة عدداً صحيحاً'],
  [/must be at least (\d+)/i, (m) => `يجب ألا تقل القيمة عن ${m[1]}`],
  [/selected .* is invalid/i, () => 'القيمة المختارة غير صالحة'],
]

export function localizeValidation(message) {
  const text = String(message ?? '')
  for (const [pattern, build] of VALIDATION_PATTERNS) {
    const match = text.match(pattern)
    if (match) return build(match)
  }
  return text
}

export function parseApiError(error) {
  const res = error?.response
  const data = res?.data
  const status = res?.status

  const raw = data?.errors || data?.error?.errors || data?.error?.details
  const fields = {}
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    for (const [key, value] of Object.entries(raw)) {
      fields[key] = localizeValidation(Array.isArray(value) ? value[0] : value)
    }
  }

  let message = data?.error?.message || data?.message
  if (!res) message = 'تعذّر الاتصال بالخادم، تأكد من تشغيله ثم أعد المحاولة'
  else if (status === 429) message = 'تم تجاوز عدد المحاولات المسموح بها، يرجى الانتظار قليلاً'
  else if (status === 403 && !data?.error?.message) message = 'ليس لديك صلاحية للقيام بهذه العملية'
  else if (status === 422 && Object.keys(fields).length) message = 'البيانات المدخلة غير صحيحة، يرجى مراجعة الحقول'
  else if (!message || /^[\x20-\x7E]+$/.test(message)) {
    message = status >= 500 ? 'حدث خطأ في الخادم، حاول لاحقاً' : message || 'حدث خطأ غير متوقع'
  }

  const header = Number(res?.headers?.['retry-after'])
  const retryAfter = status === 429 ? (Number.isFinite(header) && header > 0 ? header : 60) : null
  const remaining = Number(res?.headers?.['x-ratelimit-remaining'])

  return {
    status,
    code: data?.error?.code || null,
    message,
    fields,
    retryAfter,
    remaining: Number.isFinite(remaining) ? remaining : null,
  }
}