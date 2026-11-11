<script setup>
import { reactive, ref, computed, onBeforeUnmount } from 'vue'
import Icon from '../components/Icon.vue'
import ProductCard from '../components/ProductCard.vue'
import { useProductStore } from '../stores/products'
import { useUiStore } from '../stores/ui'
import { BASE_CATEGORIES, categoryLabel } from '../utils/constants'

const products = useProductStore()
const ui = useUiStore()

const empty = () => ({ name: '', description: '', price: '', category: '', initial_stock: '', image: null })
const form = reactive(empty())
const errors = ref({})
const previewUrl = ref(null)
const created = ref(null)
const fileInput = ref(null)
const dragging = ref(false)

const preview = computed(() => ({
  name: form.name,
  description: form.description,
  price: form.price || 0,
  category: form.category || 'Electronics',
  available_quantity: Number(form.initial_stock) || 0,
}))

function setImage(file) {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  form.image = file || null
  previewUrl.value = file ? URL.createObjectURL(file) : null
  if (file) delete errors.value.image
}
function onFile(e) { setImage(e.target.files?.[0]) }
function onDrop(e) { dragging.value = false; setImage(e.dataTransfer.files?.[0]) }
function removeImage() { setImage(null); if (fileInput.value) fileInput.value.value = '' }
onBeforeUnmount(() => previewUrl.value && URL.revokeObjectURL(previewUrl.value))

async function submit() {
  created.value = null
  errors.value = {}
  try {
    const product = await products.createProduct({ ...form })
    created.value = product
    ui.success(`تمت إضافة «${product.name}» وتحديد كميته في المخزون`)
    Object.assign(form, empty())
    removeImage()
  } catch (err) {
    errors.value = err.fields || {}
    if (!Object.keys(errors.value).length) ui.error(err.message)
  }
}
</script>

<template>
  <div class="space-y-8">
    <section>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-white">إضافة منتج جديد</h2>
      <p class="text-sm text-slate-400 mt-1">أدخل تفاصيل المنتج والكمية الأولية في المستودع، وستظهر المعاينة بجانب النموذج.</p>
    </section>

    <div v-if="created" class="rounded-2xl bg-emerald-950/30 border border-emerald-800/60 text-emerald-200 p-4 flex flex-wrap items-center justify-between gap-3">
      <span class="flex items-center gap-2 text-sm font-bold"><Icon name="check" size="w-5 h-5" />تمت إضافة «{{ created.name }}» (رقم {{ created.id }}) بنجاح</span>
      <router-link to="/" class="btn btn-ghost text-xs">عرض المنتجات</router-link>
    </div>

    <div class="grid lg:grid-cols-[1fr_20rem] gap-8 items-start">
      <form class="card p-6 sm:p-8 space-y-6" novalidate @submit.prevent="submit">
        <div>
          <label class="label" for="name">اسم المنتج</label>
          <input id="name" v-model="form.name" type="text" placeholder="مثال: هاتف ذكي Samsung" class="input" :class="{ 'input-error': errors.name }" />
          <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-6">
          <div>
            <label class="label" for="category">التصنيف</label>
            <input id="category" v-model="form.category" list="categories" type="text" dir="ltr" placeholder="Electronics" class="input text-start" :class="{ 'input-error': errors.category }" />
            <datalist id="categories"><option v-for="c in BASE_CATEGORIES" :key="c" :value="c">{{ categoryLabel(c) }}</option></datalist>
            <p v-if="errors.category" class="field-error">{{ errors.category }}</p>
          </div>
          <div>
            <label class="label" for="price">السعر ($)</label>
            <input id="price" v-model="form.price" type="number" step="0.01" min="0" placeholder="0.00" class="input" :class="{ 'input-error': errors.price }" />
            <p v-if="errors.price" class="field-error">{{ errors.price }}</p>
          </div>
        </div>

        <div>
          <label class="label" for="stock">الكمية الأولية في المستودع</label>
          <input id="stock" v-model="form.initial_stock" type="number" min="0" step="1" placeholder="مثال: 50" class="input" :class="{ 'input-error': errors.initial_stock }" />
          <p v-if="errors.initial_stock" class="field-error">{{ errors.initial_stock }}</p>
        </div>

        <div>
          <label class="label" for="description">الوصف</label>
          <textarea id="description" v-model="form.description" rows="4" placeholder="اكتب وصفاً مختصراً للمنتج..." class="input resize-none" :class="{ 'input-error': errors.description }"></textarea>
          <p v-if="errors.description" class="field-error">{{ errors.description }}</p>
        </div>

        <div>
          <label class="label">صورة المنتج (اختيارية)</label>
          <div
            class="rounded-2xl border-2 border-dashed p-6 text-center transition cursor-pointer"
            :class="dragging ? 'border-purple-500 bg-purple-500/10' : errors.image ? 'border-rose-500/60' : 'border-slate-700 hover:border-slate-600'"
            @click="fileInput.click()"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="onDrop"
          >
            <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onFile" />
            <template v-if="form.image">
              <p class="text-sm text-slate-200 font-bold truncate">{{ form.image.name }}</p>
              <button type="button" class="text-xs text-rose-300 font-bold mt-2" @click.stop="removeImage">إزالة الصورة</button>
            </template>
            <template v-else>
              <Icon name="photo" size="w-8 h-8 mx-auto text-slate-500" />
              <p class="text-sm text-slate-300 mt-2">اسحب الصورة هنا أو اضغط للاختيار</p>
              <p class="text-[11px] text-slate-500 mt-1">JPG أو PNG أو WEBP — حتى 2 ميغابايت</p>
            </template>
          </div>
          <p v-if="errors.image" class="field-error">{{ errors.image }}</p>
        </div>

        <p v-if="products.error && !Object.keys(errors).length" class="rounded-xl bg-rose-950/30 border border-rose-800 text-rose-300 p-3 text-sm">{{ products.error }}</p>

        <button type="submit" class="btn btn-primary w-full py-3" :disabled="products.saving">
          {{ products.saving ? 'جاري الحفظ...' : 'حفظ المنتج' }}
        </button>
      </form>

      <aside class="lg:sticky lg:top-28 space-y-3">
        <p class="text-xs font-bold text-slate-400">معاينة البطاقة</p>
        <ProductCard :product="preview" :preview-url="previewUrl" readonly />
      </aside>
    </div>
  </div>
</template>
