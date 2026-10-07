

# Railway

## Database
### Exportar dump
pg_dump \
  "postgresql://postgres:{pswd}@{host}:{port}/{database}" \
  --format=custom \
  --no-owner \
  --file=backup.dump

### Restaurar dump
pg_restore \
  "postgresql://postgres:{pswd}@{host}:{port}/{database}" \
  --no-owner \
  backup.dump



### Exportar dump sql
     pg_dump \
  "postgresql://postgres:{pswd}@{host}:{port}/{database}" \
  --no-owner \
  --no-privileges \
  --file="backup-$(date +%Y-%m-%d_%H-%M).sql"
