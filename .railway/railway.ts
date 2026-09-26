import { defineRailway, github, postgres, preserve, project, service, volume } from "railway/iac";

export default defineRailway(() => {
  const Postgres = postgres("Postgres", { region: "europe-west4-drams3a" });
  Postgres.networking = { privateNetworkEndpoint: "postgres" };
  const postgresVolume = volume("postgres-volume", { alerts: { usage: { "100": {}, "80": {}, "95": {} } }, allowOnlineResize: true, region: "europe-west4-drams3a", sizeMB: 500 });
  const wildforceBack = service("wildforce-back", {
    source: github("BadChoice/wildforce-back", { checkSuites: false }),
    replicas: { "europe-west4-drams3a": 1 },
    healthcheck: "/up",
    env: {
      APP_DEBUG: "false",
      APP_ENV: "production",
      APP_FAKER_LOCALE: preserve(),
      APP_FALLBACK_LOCALE: preserve(),
      APP_KEY: preserve(),
      APP_LOCALE: preserve(),
      APP_MAINTENANCE_DRIVER: preserve(),
      APP_NAME: preserve(),
      APP_URL: preserve(),
      BCRYPT_ROUNDS: preserve(),
      BROADCAST_CONNECTION: preserve(),
      CACHE_STORE: preserve(),
      DB_CONNECTION: "pgsql",
      DB_URL: Postgres.env.DATABASE_URL,
      FILESYSTEM_DISK: preserve(),
      LOG_CHANNEL: "stderr",
      LOG_DEPRECATIONS_CHANNEL: preserve(),
      LOG_LEVEL: preserve(),
      LOG_STACK: preserve(),
      MAIL_MAILER: preserve(),
      MEMCACHED_HOST: preserve(),
      PORT: "8080",
      QUEUE_CONNECTION: preserve(),
      RAILWAY_DOCKERFILE_PATH: "ci/Dockerfile",
      REDIS_CLIENT: preserve(),
      REDIS_HOST: preserve(),
      REDIS_PASSWORD: preserve(),
      REDIS_PORT: preserve(),
      SESSION_DOMAIN: preserve(),
      SESSION_DRIVER: preserve(),
      SESSION_ENCRYPT: preserve(),
      SESSION_LIFETIME: preserve(),
      SESSION_PATH: preserve(),
      SESSION_SECURE_COOKIE: "true",
    },
  });

  return project("authentic-harmony", {
    resources: [wildforceBack, Postgres, postgresVolume],
  });
});
