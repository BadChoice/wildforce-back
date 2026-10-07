

# Railway

## Commands
Once the railway is linked to the project, to run a command simply:
```bash
railway run php artisan migrate
```

>**Important:** `railway run` doesn't run PHP in the Railway container; runs your local PHP with visible Railway variables. To run in the railway container it's another procedure.

```bash
railway ssh php artisan migrate --force
```

## Database

Create a tunnel so you can access through the local machine:

```bash
railway connect --tunnel-only postgres
```
It will display the connection string to use in the following commands

### Export dump
```bash
pg_dump \
  "postgresql://postgres:{pswd}@{host}:{port}/{database}" \
  --format=custom \
  --no-owner \
  --file=backup.dump
```

### Restore dump
```bash
pg_restore \
  "postgresql://postgres:{pswd}@{host}:{port}/{database}" \
  --no-owner \
  backup.dump
```



### Export dump in sql
```bash
pg_dump \
  "postgresql://postgres:{pswd}@{host}:{port}/{database}" \
  --no-owner \
  --no-privileges \
  --file="backup-$(date +%Y-%m-%d_%H-%M).sql"
```
