<script setup>
import { reactive, ref, watch, onBeforeUnmount } from 'vue'
import Icon from './Icon.vue'
import { useProductStore } from '../stores/products'
import { useUiStore } from '../stores/ui'
import { BASE_CATEGORIES, categoryLabel } from '../utils/constants'

const props = defineProps({ product: { type: Object, default: null } })
const emit = defineEmits(['close', 'saved'])

const products = useProductStore()
const ui = useUiStore()

const form = reactive({ name: '', description: '', price: '', category: '', initial_stock: '', image: null })
const errors = ref({})
const saving = ref(false)

const onKey = (e) => { if (e.key === 'Escape') emit('close') }

watch(
  () => props.product,
  (p) => {
    errors.value = {}
    if (p) {
      Object.assign(form, {
        name: p.name ?? '',
        description: p.description ?? '',
        price: p.price ?? '',
        category: p.category ?? '',
        initial_stock: p.available_quantity ?? 0,
        image: null,
      })
      document.body.style.overflow = 'hidden'
      window.addEventListener('keydown', onKey)
    } else {
      document.body.style.overflow = ''
      window.removeEventListener('keydown', onKey)
    }
  },
  { immediate: true }
)
onBeforeUnmount(() => {
  document.body.style.overflow = ''
  window.removeEventListener('keydown', onKey)
})

async function submit() {
  if (saving.value) return
  saving.value = true
  errors.value = {}
  try {
    const { stockSynced } = await products.updateProduct(props.product.id, { ...form })
    if (stockSynced) ui.success(`تم تحديث «${form.name}» بنجاح`)
    else ui.error('تم حفظ بيانات المنتج لكن كمية المخزون لم تتغيّر في Inventory Service، راجع الباك')
    emit('saved')
    emit('close')
  } catch (err) {
    errors.value = err.fields || {}
    if (!Object.keys(errors.value).length) ui.error(err.message)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="product" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center bg-black/70 backdrop-blur-sm sm:p-4" @click.self="emit('close')">
        <form class="w-full max-w-lg max-h-[92vh] overflow-y-auto custom-scrollbar bg-slate-900 border border-slate-700 rounded-t-3xl sm:rounded-3xl shadow-2xl" role="dialog" aria-modal="true" novalidate @submit.prevent="submit">
          <div class="sticky top-0 z-10 flex items-center justify-between px-5 py-4 bg-slate-900/95 backdrop-blur border-b border-slate-800">
            <h3 class="text-lg font-extrabold text-white">تعديل المنتج</h3>
            <button type="button" class="w-9 h-9 grid place-items-center rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300" aria-label="إغلاق" @click="emit('close')">
              <Icon name="x" size="w-4 h-4" />
            </button>
          </div>

          <div class="p-5 space-y-5">
            <div>
              <label class="label" for="edit-name">اسم المنتج</label>
              <input id="edit-name" v-model="form.name" type="text" class="input" :class="{ 'input-error': errors.name }" />
              <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
              <div>
                <label class="label" for="edit-category">التصنيف</label>
                <input id="edit-category" v-model="form.category" list="edit-categories" type="text" dir="ltr" class="input text-start" :class="{ 'input-error': errors.category }" />
                <datalist id="edit-categories"><option v-for="c in BASE_CATEGORIES" :key="c" :value="c">{{ categoryLabel(c) }}</option></datalist>
                <p v-if="errors.category" class="field-error">{{ errors.category }}</p>
              </div>
              <div>
                <label class="label" for="edit-price">السعر ($)</label>
                <input id="edit-price" v-model="form.price" type="number" step="0.01" min="0" class="input" :class="{ 'input-error': errors.price }" />
                <p v-if="errors.price" class="field-error">{{ errors.price }}</p>
              </div>
            </div>

            <div>
              <label class="label" for="edit-stock">الكمية في المخزون</label>
              <input id="edit-stock" v-model="form.initial_stock" type="number" min="0" step="1" class="input" :class="{ 'input-error': errors.initial_stock }" />
              <p v-if="errors.initial_stock" class="field-error">{{ errors.initial_stock }}</p>
            </div>

            <div>
              <label class="label" for="edit-description">الوصف</label>
              <textarea id="edit-description" v-model="form.description" rows="3" class="input resize-none" :class="{ 'input-error': errors.description }"></textarea>
              <p v-if="errors.description" class="field-error">{{ errors.description }}</p>
            </div>

            <div>
              <label class="label" for="edit-image">صورة جديدة (اتركيه فارغاً للإبقاء على الحالية)</label>
              <input id="edit-image" type="file" accept="image/jpeg,image/png,image/webp" class="input" @change="form.image = $event.target.files?.[0] || null" />
              <p v-if="errors.image" class="field-error">{{ errors.image }}</p>
            </div>

            <div class="flex gap-3 pt-1">
              <button type="button" class="btn btn-ghost flex-1" @click="emit('close')">إلغاء</button>
              <button type="submit" class="btn btn-primary flex-1" :disabled="saving">{{ saving ? 'جاري الحفظ...' : 'حفظ التعديلات' }}</button>
            </div>
          </div>
        </form>
      </div>
    </Transition>
  </Teleport>
</template>