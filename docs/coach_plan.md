# Pla d'implementació: coaches, clients i invitacions

## Decisió de producte (MVP)

- Un **coach** és un usuari amb una subscripció `coach_*` activa; no es dedueix del
  nombre de clients que té.
- Una subscripció de coach cobreix l'accés dels seus **clients actius**, fins al
  límit del pla. El client no paga cap subscripció Wildforce addicional.
- Per al MVP, un client només pot tenir **un coach actiu**. El canvi de coach és
  explícit: finalitza la relació anterior abans d'activar la nova.
- Les invitacions pendents no ocupen plaça. La plaça s'ocupa només en acceptar-la.
- `active` dona accés i ocupa plaça; `paused` i `ended` preserven l'historial però
  no donen accés ni ocupen plaça.
- En aquesta fase no es cobra el servei del coach dins de Wildforce. Això és
  facturació coach-client i queda fora del SaaS que el coach paga a Wildforce.

## Regla d'accés resultant

```text
pot gestionar clients = subscripció coach activa

té accés a l'app = subscripció individual activa
                   O enrollment actiu amb coach de subscripció coach activa
```

Aquest càlcul evita sobreescriure una subscripció individual si un client també
rep accés patrocinat pel seu coach.

## Principis de lliurament

- Cada tasca és desplegable per si sola i acaba amb les proves rellevants a verd.
- Afegir primer les noves abstraccions i migrar els consumidors; no canviar el
  significat de `isCoach()` de forma silenciosa a mitja implementació.
- Tota comprovació de capacitat i d'unicitat s'ha de fer al servidor dins d'una
  transacció; la UX és informativa, no és la font d'autoritat.
- Després de cada tasca: executar les proves que s'indiquen, `composer lint:check`
  per als fitxers modificats, i ampliar al conjunt de proves de la funcionalitat
  abans de fusionar.

---

## 0. Fixar el contracte i la xarxa de seguretat

**Objectiu:** deixar les decisions del MVP convertides en proves abans de tocar
autorització o facturació.

1. Crear proves de domini per a:
   - un coach amb pla coach actiu i zero clients;
   - un usuari amb clients però sense pla coach actiu;
   - un client amb accés propi;
   - un client amb accés només via enrollment actiu;
   - un client amb enrollment pausat o finalitzat.
2. Inventariar totes les lectures de `User::isCoach()` i de
   `clients(Active)->exists()` que signifiquin permisos, no dades de pantalla.
3. Afegir a la documentació de l'API la semàntica temporal de `has_access`:
   accés individual o accés patrocinat.

**Verd:** proves de domini noves + `php artisan test --filter=Subscription`.

## 1. Centralitzar els entitlements del coach

**Objectiu:** tenir una única font de veritat per saber qui pot fer accions de
coach i quin màxim de clients té.

1. Afegir al `SubscriptionPlan` mètodes explícits, per exemple:
   - `isCoachPlan(): bool`;
   - `activeClientLimit(): ?int` (`null` només per a un futur pla il·limitat).
2. Crear un servei o value object (`CoachEntitlement`) que, a partir de l'usuari,
   exposi `canManageClients`, `activeClientLimit` i `activeClientCount`.
3. Afegir `User::hasCoachEntitlement()` com a adaptador curt cap al servei;
   deprecar `isCoach()` i substituir-ne els usos d'autorització.
4. Definir inicialment les capacitats de Basic i Pro en una configuració central;
   no codificar-les als components Livewire.
5. Actualitzar polítiques, plantilles i catàleg d'exercicis perquè autoritzin amb
   l'entitlement, no amb l'existència de clients.

**Verd:** proves de `CoachEntitlement`, polítiques afectades i
`php artisan test --filter='(WorkoutTemplates|DataAccessPolicy|Subscription)'`.

## 2. Fer explícit l'accés patrocinat del client

**Objectiu:** separar el dret d'accés del client de la seva subscripció de
facturació, mantenint compatible l'esquema actual d'una subscripció per usuari.

1. Extreure `AppAccessResolver` (o nom equivalent) amb:
   - subscripció individual activa;
   - enrollment actiu;
   - coach amb entitlement actiu.
2. Canviar `User::hasAppAccess()` i `SubscriptionAccessController` perquè usin
   el resolver. Retornar, si és útil al client, `access_source` (`self` o
   `coach`) sense exposar dades de pagament del coach.
3. Tractar `SubscriptionPlan::CoachedExternal` com a llegat: les dades existents
   mantenen l'accés, però els nous enrollments no creen ni reemplacen una
   subscripció del client amb aquest pla.
4. Escriure una migració de dades només quan s'hagi validat en producció com es
   van crear els `coached_external`; no eliminar-lo en aquest lliurament.

**Verd:** proves de l'API `/subscription/access`, middleware
`EnsureUserHasAppAccess` i proves de regressió de subscripcions existents.

## 3. Reforçar la integritat dels enrollments

**Objectiu:** aplicar la regla d'un sol coach actiu abans d'obrir el flux
d'invitació.

1. Introduir un servei `CoachingEnrollmentService` amb operacions
   `activate`, `pause`, `end` i `changeCoach`.
2. En activar, validar dins d'una transacció:
   - coach amb entitlement actiu;
   - capacitat disponible;
   - el client no té un altre enrollment actiu.
3. Afegir una protecció de base de dades adequada al motor desplegat. Si no es
   disposa d'índex parcial per estat, usar una columna normalitzada de
   `active_client_user_id` nul·la fora d'`active`, amb índex únic, o un altre
   mecanisme equivalent. Documentar l'elecció.
4. Mantenir l'historial: no posar un únic índex sobre `(coach_user_id,
   client_user_id)`, ja que un client pot tornar a ser-ho més endavant.
5. Fer que `paused`/`ended` alliberin plaça i que `ended` registri `ends_at`.

**Verd:** proves de concurrència/capacitat, canvi de coach i restricció d'un
coach actiu; migracions en una base de dades buida i sobre un fixture amb dades.

## 4. Afegir invitacions persistents i segures

**Objectiu:** modelar la invitació abans de crear pantalles o correus.

1. Crear `coaching_invitations` amb, com a mínim:
   `id`, `coach_user_id`, `email`, `name`, `message`, `token_hash`,
   `expires_at`, `accepted_at`, `declined_at`, `revoked_at`, timestamps.
2. Crear l'enum d'estat derivat de les dates (o camps explícits, però no tots dos
   sense una regla clara) i una política perquè només el coach emissor la pugui
   consultar, reenviar o revocar.
3. Generar un token criptogràfic aleatori; desar-ne només el hash. L'URL és d'un
   sol ús, caduca inicialment als set dies i no revela el correu ni l'identificador
   del coach.
4. Implementar `InviteClient` i `AcceptCoachingInvitation` com a serveis.
   L'acceptació ha de reutilitzar el servei d'enrollments de la tasca 3, de manera
   que torna a comprovar capacitat i unicitat.
5. Afegir limitació de freqüència per crear/reenviar invitacions i impedir que un
   coach s'inviti a si mateix.

**Verd:** proves de token, caducitat, revocació, acceptació d'un sol ús,
capacitat consumida només en acceptar i impossibilitat d'acceptar com a un altre
correu si la invitació està lligada a email.

## 5. Exposar el flux web d'acceptació i registre

**Objectiu:** fer que tant un usuari nou com un existent puguin acceptar la
invitació de forma segura.

1. Crear una ruta pública mínima per resoldre el token; no activar cap enrollment
   en un `GET`.
2. Per a un usuari no autenticat, conservar el token a la sessió i enviar-lo a
   registre/inici de sessió. Un cop autenticat, tornar a la pantalla de
   confirmació.
3. Per a un usuari existent, exigir que el seu email coincideixi amb el de la
   invitació; si no coincideix, mostrar una explicació segura sense filtrar
   comptes.
4. Mostrar el nom del coach, la marca de caducitat i les accions **Acceptar** i
   **Declinar**. Si ja té coach actiu, no activar directament: oferir un flux de
   canvi clar que finalitza primer la relació anterior.
5. Mostrar missatges específics per a invitació expirada, revocada, ja utilitzada
   i pla del coach sense capacitat.

**Verd:** proves feature del recorregut registre → acceptació, login →
acceptació, declinació i canvi de coach; revisió manual dels estats d'error.

## 6. Incorporar la UX de gestió al dashboard de coach

**Objectiu:** permetre convidar i gestionar sense deixar que la UI decideixi
permisos.

1. A la vista **Clients**, mostrar capacitat (`7 de 10 clients actius`) i el botó
   `Invitar client` només si existeix l'entitlement de coach.
2. Afegir modal d'invitació: email obligatori, nom i missatge opcionals. En
   completar-lo, mostrar l'enllaç per copiar i la confirmació d'enviament.
3. Afegir secció **Invitacions pendents** amb email, caducitat, copiar enllaç,
   reenviar i revocar.
4. Afegir al detall del client les accions pausar, reprendre i finalitzar, amb
   confirmació explícita per a finalitzar.
5. Per a usuaris sense pla coach, substituir la llista editable per un estat de
   conversió clar amb enllaç a la pàgina de plans; no mostrar una llista buida que
   sembli un error.

**Verd:** proves Livewire/feature d'autorització, contingut dels estats buits i
accions de invitació; comprovació manual responsive i mode fosc.

## 7. Connectar el catàleg de plans i Stripe

**Objectiu:** comercialitzar el dret que el backend ja aplica, sense dependre de
la UI per al límit.

1. Afegir preus mensuals i anuals de `coach_basic_*` i `coach_pro_*` a
   `services.stripe.prices` i al catàleg de preus, amb variables d'entorn.
2. Confirmar que el webhook de Stripe mapeja cada `price_id` al pla coach
   correcte i conserva l'import, moneda i renovació.
3. Dissenyar upgrade/downgrade: un downgrade per sota dels clients actius ha de
   quedar programat per al final del cicle o requerir que el coach redueixi la
   cartera abans. No desactivar clients automàticament.
4. Si un pagament falla, bloquejar noves invitacions immediatament segons l'estat
   de subscripció; conservar els clients fins al final del període de gràcia que
   ja aplica el domini.
5. Afegir textos de producte que indiquin clarament que el pla del coach inclou
   l'accés dels seus clients i que Wildforce no els cobrarà una quota separada.

**Verd:** proves de Checkout, catàleg de preus, webhook i transicions de pla;
prova manual en Stripe test mode.

## 8. Desplegament progressiu i observabilitat

**Objectiu:** llançar sense bloquejar els coaches existents ni perdre accés de
clients.

1. Crear primer les migracions additives i desplegar-les abans del codi que les
   consumeix.
2. Classificar els coaches existents: assignar o verificar el seu pla coach i
   comptar clients actius abans d'aplicar límits. Qualsevol cas per sobre del nou
   límit requereix una decisió de negoci abans d'enforçar-lo.
3. Publicar el flux rere una feature flag fins que s'hagin validat emails,
   acceptacions i càlcul d'accés.
4. Registrar esdeveniments estructurats: invitació creada, enviada, acceptada,
   revocada, caducada, enrollment canviat i capacitat denegada. No registrar mai
   el token complet.
5. Preparar suport: explicació de transferència de coach, recuperació
   d'invitacions i què passa en cancel·lar el pla.

**Verd:** prova de fum en staging amb un coach nou, un existent, un client nou i
un client amb subscripció pròpia; monitoratge sense errors abans d'ampliar la
feature flag.

## Fora de l'abast del MVP

- Diversos coaches actius per client (p. ex. fitness + nutrició).
- Cobraments del servei professional del coach al client, marketplace o Stripe
  Connect.
- Equips, assistents de coach i compartició de cartera.
- Repartir permisos per àrea (només entrenament o només nutrició).

Aquestes capacitats s'han de dissenyar sobre la mateixa base d'enrollments, però
requeriran propietat separada de plans, permisos per domini de dades i una regla
d'accés diferent.
