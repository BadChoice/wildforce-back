# Millores del planificador d'entrenaments amb IA

Objectiu: que el pla generat sigui més consistent i que la IA s'utilitzi menys (menys tokens i menys crides). Principi general: **tot el que és determinista es calcula en PHP; la IA només tria i ordena exercicis.**

Fitxers principals:
- `app/Services/Workouts/WorkoutPlanAIGenerator.php`: construeix el prompt i persisteix el pla.
- `app/Ai/Agents/Workouts/WorkoutPlanGeneratorAgent.php`: instruccions i esquema de sortida.
- `app/Services/Workouts/Progression/*`: fases, anàlisi de progressió i historial.

---

## ✅ 1. Llista d'exercicis permesos truncada

**Problema:** `availableExercises()` feia `->sortBy('id')->take(60)`, de manera que només arribaven els 60 primers exercicis per ordre alfabètic (fins a `lateralRaise`). En quedaven fora `pullUp`, `pushUp`, `overheadPress`, `romanianDeadlift`, `seatedCableRow`, `tricepsPushdown`… però el prompt els demanava com a anchors. El mateix bug era a `SingleWorkoutAIGenerator`.

**Fet:**
- S'ha tret el `take(60)` dels dos generadors.
- Per compensar els tokens, s'exclouen els exercicis de categoria `mobility` quan l'usuari no vol ni warmups ni cooldowns, l'objectiu no és `improveMobility` i cap dia no té focus `mobility`.

## ✅ 2. Fases explícites i numèriques

**Problema:** el text "week 2 of 6" barrejava la setmana dins la fase amb la llargada del cicle. La prescripció per a objectius que no eren de força era vaga ("challenging but controlled loads"): no hi havia RIR, sèries per exercici ni progressió dins la fase, i el deload només parlava de volum.

**Fet:**
- `MesocyclePhaseRules::phaseLength()` retorna la durada de cada fase.
- La línia de fase ara diu, per exemple: `intensification week 2 of 2 (cycle week 5 of 6). Next week: deload`.
- La nova classe `PhasePrescription` genera la secció `## Phase prescription` amb:
  - sèries, reps, RIR i descans per als exercicis principals i els accessoris, per fase × objectiu (força vs. la resta);
  - un RIR que baixa setmana a setmana dins la fase (interpolació entre la primera i l'última setmana);
  - el volum i la càrrega relatius a la fase anterior, amb el deload a ~85–90 % de la càrrega i ~40–50 % menys sèries;
  - l'ajust per composició corporal (`cut`: sense sèries extra, sense arribar al fallo als exercicis principals; `bulk`: volum a la part alta del rang).
- La prioritat 2 de les instruccions de l'agent apunta a aquesta secció.

**Pendent / possible ampliació:**
- [ ] Afinar els paràmetres per a `improveEndurance` i `improveMobility`, que ara fan servir la taula general.
- [ ] Quan la readiness és `low`, aplicar l'ajust al codi (p. ex. RIR +1 i sèries al mínim del rang) en lloc de deixar-ho a la IA.

## ✅ 2b. Escalfament amb sèries d'aproximació

**Problema:** el warmup s'omplia amb drills de mobilitat genèrics. Al gimnàs, l'escalfament útil és l'específic: sèries d'aproximació del primer exercici principal.

**Fet:**
- L'agent fa el bloc `warmup` amb sèries d'aproximació (estil `warmup`): 2-4 sèries, reps que baixen i càrrega del ~40 % al ~80 % de la de treball. Opcionalment, 3-5 min de cardio suau.
- El cooldown, quan es demana, és de ~5 min de cardio suau, mobilitat o estiraments dels músculs treballats.
- `TrainingPreference::needsMobilityExercises()` ja no té en compte els warmups. La mobilitat entra al catàleg només per cooldowns, per l'objectiu `improveMobility` o per un dia amb focus `mobility`.
- `TrainingHistory` ignora els exercicis dels blocs `warmup`, perquè les sèries d'aproximació no embrutin les tendències, el volum ni el feedback.

**Pendent:**
- [ ] Generar les sèries d'aproximació en PHP a partir de la càrrega de treball (amb el pas 6).

---

## ✅ 3. Netejar soroll i dades poc fiables del prompt

**Fet:**
- L'alçada i el pes surten com a `unknown` quan no n'hi ha (abans `Weight: 0.00 kg`).
- El `Completion rate` surt en percentatge arrodonit (`78%`).
- **Exercise trends** i **anchors**: només exercicis de resistència amb una tendència real (sense `insufficient`, mobilitat ni cardio).
- **Exercise profiles**: només per als exercicis sense resultats a l'historial recent, i sense camps buits (`0 kg`, `max reps: 0`). Els perfils que queden buits no s'envien.
- **Historial recent**:
  - s'ometen els dies sense cap resultat (`planned` i `skipped` buits) i els plans que queden buits;
  - cada dia mostra el focus i l'estat: `push (completed): …`;
  - cada exercici mostra què estava prescrit i què es va fer: `benchPress (planned 4×6-8 @ 40 kg; did 4×8 @ 40 kg, justRight)`;
  - els pesos van sense decimals sobrers (`37.5 kg`, `40 kg`).

## ✅ 4. Revisar el càlcul de tendències

**Problema:** `progressionTrend()` comparava el pes mitjà recent amb l'anterior. `completed_weight` i `completed_reps` són només els de l'última sèrie, de manera que una sèrie de back-off o una dada mal entrada distorsionava la tendència. A més, el feedback no comptava i només hi havia tendència per als exercicis amb perfil (p. ex. `benchPress` en quedava fora si no tenia perfil).

**Fet:** nova classe `ExerciseTrendCalculator`.
- Cada sessió es puntua per la millor sèrie (`per_set_reps` × `per_set_weights_kg`, amb `completed_*` com a alternativa):
  - amb càrrega: e1RM d'Epley, `pes × (1 + (reps + RIR) / 30)`;
  - pes corporal: `reps + RIR`.
- El RIR surt del feedback (`veryEasy` 4, `easy` 3, `justRight` 2, `hard` 1, `veryHard` 0). El mateix pes i les mateixes reps amb `hard` puntuen menys que amb `easy`.
- Es descarten els valors aïllats que es desvien més d'un 40 % de l'anterior i del següent en la mateixa direcció (p. ex. 30 → 16 → 30 kg).
- Es compara la mitjana de les últimes sessions (fins a 3) amb la de les anteriors (fins a 3), sense solapar-les. El llindar és ±2,5 % en e1RM, o ±1 rep per al pes corporal.
- `TrainingHistory` carrega els resultats de tots els exercicis fets (no només els que tenen perfil), i `exerciseTrends()` inclou perfils i exercicis fets.

**Pendent:**
- [ ] Un valor erroni a l'última sessió no es pot detectar (no té veí posterior). Es podria marcar en lloc de descartar-lo.
- [ ] L'historial del prompt mostra `did 3×10` amb les reps de l'última sèrie. Es podrien mostrar les reps de cada sèrie quan varien (`12/8/10`).

## ✅ 5. Un estat únic per exercici

**Problema:** hi havia tres llistes que se solapaven (trends, anchors, rotate). Per exemple, `calfRaise` sortia alhora a anchors i a rotate, i les instruccions necessitaven un paràgraf per desempatar.

**Fet:**
- `ExerciseStatusResolver` dona un sol estat (`ExerciseStatus`) a cada exercici de resistència fet recentment (sense mobilitat ni cardio). Les regles s'apliquen en aquest ordre:
  1. `reduce`: tendència `regressing` o últim feedback `veryHard`.
  2. `rotate`: `plateau`, fet als 3 últims plans actius i **la setmana comença una fase nova** (no deload). Dins d'una fase es manté la selecció d'exercicis; sense periodització es pot rotar qualsevol setmana.
  3. `progress`: tendència `improving` o últim feedback `easy`/`veryEasy`.
  4. `keep`: la resta.
- El prompt envia una sola taula `## Exercise status`: `exercise | status | last performance (best set) | trend`. La millor sèrie surt d'`ExerciseTrendCalculator::bestSet()`.
- S'han tret les línies `Exercise trends`, `Keep as anchors` i `Rotate when practical`.
- Instruccions: les antigues prioritats 4 i 5 són ara una sola prioritat que explica què fer amb cada estat.

**Pendent:**
- [ ] Afegir la columna `suggested next` amb la progressió calculada (pas 6).

## ⬜ 6. Progressió calculada en PHP

- [ ] Regla de doble progressió per exercici amb historial:
  - `veryEasy`/`easy`, o `justRight` a la part alta del rang → +càrrega (increment segons l'equipament: barra +2,5 kg, manuelles +1–2 kg, cable el següent pas de la màquina);
  - `justRight` dins del rang → +1 rep;
  - `hard` → mantenir;
  - `veryHard` o regressió → baixar la càrrega un 5–10 % o substituir l'exercici.
- [ ] Aplicar els paràmetres de `PhasePrescription` (rang de reps i RIR) en canviar de fase.
- [ ] Passar a la IA la prescripció suggerida ja calculada (`benchPress → 4×6 @ 37.5 kg, RIR 1`).
- [ ] Opcional: que la IA només retorni exercicis i ordre, i que PHP ompli sèries, reps i càrregues.

## ⬜ 7. No cridar la IA dins d'una mateixa fase (estalvi més gran)

- [ ] Setmanes 2+ d'una fase: clonar l'estructura de la setmana anterior i aplicar-hi la progressió del pas 6, sense IA.
- [ ] Deload: clonar l'última setmana activa i aplicar-hi la reducció de sèries i càrrega, sense IA.
- [ ] Cridar la IA només a l'inici de cada fase, quan l'usuari canvia preferències (dies, equipament, split, restriccions) o quan hi ha prou exercicis marcats com `rotate`.
- Impacte: amb un cicle de 6 setmanes es passa de 6 crides a 2–3.

## ⬜ 8. Catàleg filtrat per dia

- [ ] Agrupar els exercicis permesos per focus del dia (push/pull/legs/upper/lower/fullBody) a partir de `movementPatterns` i `primaryMuscles`, en lloc d'enviar una llista plana.
- [ ] Ometre les columnes que no aporten res per a cada exercici (p. ex. `targets` quan és `reps, weight`).

## ⬜ 9. Instruccions i cost

- [ ] Quan els passos 3–6 estiguin fets, escurçar les instruccions de l'agent (una bona part de les regles en prosa deixen de ser necessàries).
- [ ] Ordenar el prompt perquè la part estable (instruccions i catàleg) vagi primer i es pugui aprofitar el prompt caching del proveïdor.
- [ ] Mirar si, amb les decisions ja precalculades, un model més petit o un `reasoning.effort` més baix donen el mateix resultat.
