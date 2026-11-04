# Build jar on host first if needed: cd inventory-service && ./gradlew bootJar -x test
# Or build inside image (slower):
FROM eclipse-temurin:21-jdk-alpine AS build
WORKDIR /app
COPY build.gradle settings.gradle gradlew ./
COPY gradle gradle
COPY proto proto
COPY src src
RUN chmod +x gradlew && ./gradlew bootJar -x test --no-daemon

FROM eclipse-temurin:21-jre-alpine
WORKDIR /app
COPY --from=build /app/build/libs/*.jar app.jar
RUN addgroup -S app && adduser -S app -G app && chown app:app app.jar
USER app
EXPOSE 8080 9090
ENTRYPOINT ["java", "-jar", "app.jar"]
