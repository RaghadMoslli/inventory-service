<script setup>
import { ref, computed } from 'vue'
import Icon from './Icon.vue'
import { imageUrl, formatMoney } from '../utils/format'
import { categoryLabel } from '../utils/constants'
import { useAuthStore } from '../stores/auth'
const authStore = useAuthStore()
const props = defineProps({
  product: { type: Object, required: true },
  previewUrl: { type: String, default: null }, // لمعاينة صورة لم تُرفع بعد (صفحة الأدمن)
  readonly: Boolean,
})
const emit = defineEmits(['open'])

const broken = ref(false)
const src = computed(() => props.previewUrl || imageUrl(props.product.image_path))
const stock = computed(() => Number(props.product.available_quantity ?? 0))
const stockInfo = computed(() => {
  if (stock.value <= 0) return { text: 'نفد المنتج', cls: 'bg-rose-500/10 text-rose-300 border-rose-500/25' }
  if (stock.value <= 5) return { text: `كمية محدودة: ${stock.value}`, cls: 'bg-amber-500/10 text-amber-300 border-amber-500/25' }
  return { text: `المتوفر: ${stock.value}`, cls: 'bg-indigo-500/10 text-indigo-300 border-indigo-500/25' }
})
</script>

<template>
  <article class="card overflow-hidden flex flex-col group hover:border-slate-700 transition">
    <div class="relative aspect-[4/3] bg-slate-900 overflow-hidden grid place-items-center">
      <img v-if="src && !broken" :src="src" :alt="product.name" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" @error="broken = true" />
      <div v-else class="flex flex-col items-center gap-1 text-slate-600">
        <Icon name="photo" size="w-10 h-10" />
        <span class="text-[11px]">بدون صورة</span>
      </div>
      <span class="absolute top-3 end-3 text-[11px] font-bold px-2.5 py-1 rounded-full bg-slate-950/80 text-purple-300 backdrop-blur border border-slate-700">
        {{ categoryLabel(product.category) }}
      </span>
    </div>

    <div class="p-5 flex-1 flex flex-col">
      <h3 class="font-bold text-white text-base line-clamp-1">{{ product.name || 'اسم المنتج' }}</h3>
      <p class="text-xs text-slate-400 leading-6 line-clamp-2 mt-1.5 min-h-[3rem]">{{ product.description || 'لا يوجد وصف' }}</p>

      <div class="mt-auto pt-4">
        <div class="flex items-center justify-between gap-2 pb-4">
          <span class="text-emerald-400 font-extrabold text-lg">{{ formatMoney(product.price) }}</span>
          <span class="text-[11px] font-bold px-2.5 py-1 rounded-full border" :class="stockInfo.cls">{{ stockInfo.text }}</span>
        </div>
    <button v-if="!readonly" class="btn btn-primary w-full text-xs" @click="emit('open', product)">
  <Icon name="cart" size="w-4 h-4" />
  {{ authStore.isAdmin ? 'عرض التفاصيل' : (stock > 0 ? 'التفاصيل والطلب' : 'عرض التفاصيل') }}
</button>
      </div>
    </div>
  </article>
</template>
