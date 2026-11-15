# ==========================================
# المرحلة 1: بناء تطبيق Vue / Vite (Build Stage)
# ==========================================
FROM node:20-alpine AS build

WORKDIR /app

# نسخ ملفات التبعيات أولاً للاستفادة من الـ Caching في Docker
COPY package*.json ./

# تثبيت المكتبات
RUN npm install

# نسخ باقي كود المشروع
COPY . .

# Laravel routes are under /api/v1 — bake this into the Vite build
ARG VITE_API_URL=http://localhost:8000/api/v1
ENV VITE_API_URL=$VITE_API_URL

RUN npm run build

# ==========================================
# المرحلة 2: تشغيل الخادم الخفيف Nginx (Production Stage)
# ==========================================
FROM nginx:alpine

# نسخ المخرجات المبنّية من المرحلة الأولى إلى مجلد Nginx الافتراضي
COPY --from=build /app/dist /usr/share/nginx/html

# نسخ إعدادات Nginx المخصصة لدعم الـ SPA Routing (مهمة جداً لـ Vue Router)
COPY nginx.conf /etc/nginx/conf.d/default.conf

# فتح المنفذ 80 داخل الحاوية
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]