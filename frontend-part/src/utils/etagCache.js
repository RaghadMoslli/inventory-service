/** In-memory ETag store so repeat GETs can send If-None-Match and receive 304. */
const etags = new Map()

function requestKey(config) {
  const method = (config.method || 'get').toLowerCase()
  const url = config.url || ''
  const params = config.params ? JSON.stringify(config.params) : ''
  return `${method}:${url}?${params}`
}

export function getEtag(config) {
  return etags.get(requestKey(config))
}

export function setEtag(config, etag) {
  if (!etag) return
  etags.set(requestKey(config), etag)
}

export function clearEtags() {
  etags.clear()
}
