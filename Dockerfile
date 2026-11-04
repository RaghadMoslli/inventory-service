# Build the jar first on the host: ./gradlew bootJar -x test
FROM eclipse-temurin:21-jre-alpine
WORKDIR /app

COPY build/libs/*.jar app.jar

RUN addgroup -S app && adduser -S app -G app && chown app:app app.jar
USER app

EXPOSE 8080 9090

ENTRYPOINT ["java", "-jar", "app.jar"]
