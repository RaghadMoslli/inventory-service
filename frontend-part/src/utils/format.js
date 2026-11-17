import { API_ORIGIN } from '../api/axios'
import { ORDER_STATUSES } from './constants'

const LOCALE = 'ar-u-nu-latn'

export const formatMoney = (amount) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(amount ?? 0))

export const formatDateTime = (value) =>
  value
    ? new Date(value).toLocaleString(LOCALE, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
    : ''

export const formatTime = (value) =>
  value ? new Date(value).toLocaleTimeString(LOCALE, { hour: '2-digit', minute: '2-digit' }) : ''

export function timeAgo(value) {
  if (!value) return ''
  const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000)
  const abs = Math.abs(seconds)
  const rtf = new Intl.RelativeTimeFormat(LOCALE, { numeric: 'auto' })
  if (abs < 45) return 'الآن'
  if (abs < 3600) return rtf.format(Math.round(seconds / 60), 'minute')
  if (abs < 86400) return rtf.format(Math.round(seconds / 3600), 'hour')
  return rtf.format(Math.round(seconds / 86400), 'day')
}

export function imageUrl(path) {
  if (!path) return null
  if (/^https?:\/\//i.test(path)) return path
  return `${API_ORIGIN}/storage/${String(path).replace(/^\/+/, '').replace(/^storage\//, '')}`
}

export const localizeNotification = (message) =>
  String(message ?? '').replace(/\b(PENDING|CONFIRMED|SHIPPED|DELIVERED|CANCELLED)\b/g, (s) => ORDER_STATUSES[s] || s)
