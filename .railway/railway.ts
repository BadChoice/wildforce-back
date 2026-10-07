import { defineRailway, github, postgres, preserve, project, service, volume, bucket, ref } from "railway/iac";

export default defineRailway(() => {
  const Postgres = postgres("Postgres", { region: "europe-west4-drams3a" });
  Postgres.networking = { privateNetworkEndpoint: "postgres" };
  const postgresVolume = volume("postgres-volume", { alerts: { usage: { "100": {}, "80": {}, "95": {} } }, allowOnlineResize: true, region: "europe-west4-drams3a", sizeMB: 500 });
  const userStorage = bucket("wildforce-user-storage", { region: "ams", });

    const wildforceBack = service("wildforce-back", {
    source: github("BadChoice/wildforce-back", { checkSuites: false }),
    replicas: { "europe-west4-drams3a": 1 },
    healthcheck: "/up",
    preDeploy: "php artisan migrate --force --no-interaction",
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

      LOG_CHANNEL: "stderr",
      LOG_DEPRECATIONS_CHANNEL: preserve(),
      LOG_LEVEL: preserve(),
      LOG_STACK: preserve(),

      MEMCACHED_HOST: preserve(),
      PORT: "8080",
      QUEUE_CONNECTION: "sync",
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

      // -------------------------
      // Mail
      // -------------------------
      MAIL_FROM_ADDRESS: "hello@wildforce.app",
      MAIL_FROM_NAME: "Wildforce",
      MAIL_MAILER: "resend",
      RESEND_API_KEY: preserve(),

      // -------------------------
      // Storage
      // -------------------------
      FILESYSTEM_DISK: "s3",
      AWS_ACCESS_KEY_ID: preserve(),
      AWS_SECRET_ACCESS_KEY: preserve(),
      AWS_DEFAULT_REGION: preserve(),
      AWS_BUCKET: preserve(),
      AWS_ENDPOINT: preserve(),
      AWS_USE_PATH_STYLE_ENDPOINT: preserve(),

      SUPABASE_S3_KEY: preserve(),
      SUPABASE_S3_SECRET: preserve(),

      // -------------------------
      // Apple
      // -------------------------
      APPLE_CLIENT_ID: "io.codepassion.doublegym",
      APPLE_TEAM_ID: "SV3ZXK4PZF",
      APPLE_KEY_ID:"AC3X8Q3LP3",
      APPLE_PRIVATE_KEY: preserve(),
      APPLE_WEB_CLIENT_ID: "io.codepassion.wildforce-web",
      APPLE_WEB_REDIRECT_URI: "https://www.wildforce.app/login",

      // -------------------------
      // Google
      // -------------------------
      GOOGLE_CLIENT_ID: preserve(),
      GOOGLE_WEB_CLIENT_ID: preserve(),
      GOOGLE_WEB_SECRET: preserve(),
      GOOGLE_PLAY_SERVICE_ACCOUNT_JSON: preserve(),
      GOOGLE_PLAY_PACKAGE_NAME: "io.codepassion.wildforce.android",

      // -------------------------
      // Stripe
      // -------------------------
      STRIPE_SECRET: preserve(),
      STRIPE_WEBHOOK_SECRET: preserve(),
      STRIPE_FRIEND_MONTHLY_PRICE_ID: "price_1UKwyNFI3Ev7T3Cmp0cpFv5K",
      STRIPE_FRIEND_YEARLY_PRICE_ID: "price_1UKwyhFI3Ev7T3CmhzLOUpVd",
      STRIPE_PREMIUM_MONTHLY_PRICE_ID: "price_1UKxmtFI3Ev7T3CmHhgi31jY",
      STRIPE_PREMIUM_YEARLY_PRICE_ID: "price_1UKxnAFI3Ev7T3CmpwpvtF3o",

      // -------------------------
      // OPEN FOOD FACTS
      // -------------------------
      OPEN_FOOD_FACTS_BASE_URL: "https://world.openfoodfacts.org",
      OPEN_FOOD_FACTS_USER_ID: "codepassion",
      OPEN_FOOD_FACTS_PASSWORD: "wildforce-codepassion-openfoodfacts",
      OPEN_FOOD_FACTS_USER_AGENT: "Wildforce/1.0 (https://wildforce.app)",
      OPEN_FOOD_FACTS_APP_NAME: "Wildforce",
      OPEN_FOOD_FACTS_APP_VERSION: "1.0",

      // -------------------------
      // AI Providers
      // -------------------------
      GEMINI_API_KEY: preserve(),
      OPENAI_API_KEY: preserve(),
      MISTRAL_API_KEY: preserve(),
      GROQ_API_KEY: preserve(),
    },
  });

  return project("authentic-harmony", {
    resources: [wildforceBack, Postgres, postgresVolume, userStorage],
  });
});
