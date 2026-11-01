# استخدام بيئة جافا الرسمية للبناء
FROM eclipse-temurin:17-jdk-alpine AS build
WORKDIR /app

# نسخ ملفات الإعدادات والـ Gradle
COPY build.gradle settings.gradle gradlew ./
COPY gradle gradle

# نسخ كود المصدر
COPY src src

# بناء المشروع وتوليد ملف الـ Jar
RUN ./gradlew bootJar -x test

# تشغيل التطبيق بنسخة خفيفة من الجافا
FROM eclipse-temurin:17-jre-alpine
WORKDIR /app
COPY --from=build /app/build/libs/*.jar app.jar

# منفذ التشغيل الخاص بسبرينغ بوت
EXPOSE 8080

# أمر تشغيل التطبيق
ENTRYPOINT ["java", "-jar", "app.jar"]