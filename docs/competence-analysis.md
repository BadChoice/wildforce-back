# Anàlisi Comparativa de Competència i Roadmap de Funcionalitats per a Wildforce

## 1. Resum Executiu i Anàlisi de la Competència (FitHub, Harbiz, Traineeks) vs. Wildforce

### 1.1. FitHub
* **Enfocament principal**: Gestió integral per a gimnasos, boutique studios i entrenadors personals (focus en reserves, gestió d'aforament, cobraments i fidelització).
* **Funcionalitats clau**:
  1. Calendari i reserves de classes/sessions.
  2. Processament de pagaments recurring i subscripcions integrades (Stripe/TPV).
  3. Control d'accessos i gestió de socis.
  4. Rutines i plans bàsics d'entrenament.
* **Valoració del mercat**: Molt ben valorat per la comoditat administrativa, facilitat de gestió de pagaments i gestió de reserves en temps real.

### 1.2. Harbiz (antic DudyFit)
* **Enfocament principal**: Software d'elit per a entrenadors personals, nutricionistes i centres de salut/fitness online i presencial.
* **Funcionalitats clau**:
  1. Dissenyador avançat d'entrenament (superconjunts, RPE/RIR, tempo, biblioteca de vídeos).
  2. Mòdul nutricional complet (macro-calculadores, plans de diàpids, receptes i intercanvi d'aliments).
  3. Xat privat integrat i automatització de check-ins / dubtes.
  4. Seguiment Mètric: Fotos de progrés, perímetres corporals, integració amb wearables (Apple Health / Google Fit).
  5. Automatització de pagaments i gestió de cobraments repetitius.
* **Valoració del mercat**: Una de les eines més ben valorades a Espanya i Llatinoamèrica per la seva interfície intuïtiva per als clients finals i l'eficiència en el seguiment diari.

### 1.3. Traineeks
* **Enfocament principal**: Plataforma "Modo Dios" per a entrenadors online centrada en la personalització extrema de marca, gamificació i comunitat.
* **Funcionalitats clau**:
  1. **Creador de Rutines i Dietes estil Puzzle / Drag-and-Drop**: Configuracions molt visuals de blocs, exercicis, RPE, RIR, %RM, tempo i receptes.
  2. **Unboxing del Plan / Experiència d'Usuari Gamificada**: Mètrica de tonelatge mogut comparat amb coses còmiques/èpiques, RÈCORDS personals (PRs) animats.
  3. **Comunitat i Creador de Contingut**: Feed social privat, possibilitat d'enviar clàssics, reptes, concursos, fitxers i enllaços.
  4. **Store Web integrat (Link-in-bio)**: Venda directa de rutines, dietes, assessoraments o lead magnets (productes gratis per a captació).
  5. **Gestió de Cobraments i Automatitzacions**: Avisos automàtics de pagament, reintent de cobrament i bloqueig per impagament.
  6. **Generació Automàtica de la Llista de la Compra**: Basat en el pla nutricional actiu.
* **Valoració del mercat**: Extremadament ben valorat per entrenadors independents que busquen una experiència visual atractiva ("WOW factor") i diferenciat en la venda online.

---

## 2. Estat Actual de Wildforce (Baseline)

A partir de l'anàlisi del codi base (OpenAPI spec, models i rutes), **Wildforce** ja té una base tècnica sòlida:
* **Autenticació i Identitats**: Register, Login, Google, Apple ID.
* **Motor de Sync Offline-First**: PUSH/PULL i Batch Sync per a tots els recursos (Workout Plans, Nutrition Plans, Body Metrics, Photos, Subscriptions).
* **Entrenaments**: Generació de workout-plans per IA (`/api/workout-plans/generate`), suports de blocs (warmup, standard, superset, cooldown), RIR, tempo, drop-sets, etc.
* **Nutrició**: Estructura de plans de nutrició, dies, àpats i registre fotogràfic / de diari de nutrició (`nutrition-log-media`, `nutrition-log-entries`).
* **Coaching**: Relació `CoachCollection` i assignació d'alumnes/clients.
* **Fotografies de Progrés**: Pujada de fotografies privades multi-angle (`front`, `profile`, `torso`).

---

## 3. Matriu de Funcionalitats (De més imprescindible a menys imprescindible)

| Ordre | Mòdul | Funcionalitat | Estat a Wildforce | Valoració al Mercat |
| :--- | :--- | :--- | :--- | :--- |
| **1** | **Entrenament** | Creador de rutines visual (Drag-and-drop), RPE/RIR, %RM, Tempo, Superset | Parcial (Backend API + IA preparat, cal UI interactiva completa) | ⭐⭐⭐⭐⭐ (Imprescindible) |
| **2** | **Nutrició** | Creador de plans nutricionals + Generació automàtica de llista de la compra | Parcial (Estructura de dades feta, falta llista de la compra automàtica) | ⭐⭐⭐⭐⭐ (Imprescindible) |
| **3** | **Seguiment & Check-in**| Check-ins programats automatitzats, formulari de dubtes i valoracions del client | Parcial (Mètrics i fotos existeixen, falta sistema de Check-in automàtic) | ⭐⭐⭐⭐⭐ (Imprescindible) |
| **4** | **Comunicació** | Xat privat entrenador-alumne amb missatges de veu i fitxers | Pendent | ⭐⭐⭐⭐⭐ (Molt alt) |
| **5** | **Cobraments / Store**| Link-in-Bio Store, gestió de cobraments recurrents (Stripe) i bloqueig per impagament | Parcial (Iniciat amb Stripe Checkout en web) | ⭐⭐⭐⭐ (Molt alt) |
| **6** | **Gamificació / UX** | Tonelatge mogut equivalent, avisos de Rècords Personals (PRs), Unboxing de pla | Pendent | ⭐⭐⭐⭐ (Alt diferenciador) |
| **7** | **Comunitat** | Feed privat de comunitat, reptes i concursos inter-alumnes | Pendent | ⭐⭐⭐ (Mitjà) |

---

## 4. Backlog Prioritzat per a Linear (Amb Prioritat i Esforç)

Pots importar o crear directament aquestes tasques a **Linear**:

### 🎟️ WIL-1: Llista de la Compra Automàtica (Nutrició)
* **Descripció**: Generar automàticament la llista de la compra consolidada en funció dels aliments i quantitats del pla nutricional actiu de l'alumne per a la setmana/mes.
* **Prioritat**: `P1 - Urgent`
* **Esforç**: `3 Story Points (S)`
* **Mòdul**: Nutrició / App Client

---

### 🎟️ WIL-2: Sistema de Check-ins Automàtics i Qüestionaris de Seguiment
* **Descripció**: Crear un mòdul on l'entrenador pugui programar avisos i qüestionaris de seguiment periòdics (ex. cada divendres) sol·licitant pes, fotos de progrés, nivell d'energia i sensacions.
* **Prioritat**: `P1 - Urgent`
* **Esforç**: `5 Story Points (M)`
* **Mòdul**: Coaching / Check-in

---

### 🎟️ WIL-3: Xat Integrat Entrenador-Client
* **Descripció**: Implementar xat privat en temps real entre l'entrenador i l'alumne amb suport per a missatges de text, imatges i notes de veu directament a la plataforma.
* **Prioritat**: `P2 - High`
* **Esforç**: `8 Story Points (L)`
* **Mòdul**: Comunicació

---

### 🎟️ WIL-4: Gamificació: Celebració de PRs i Mètrica de Tonelatge Mogut
* **Descripció**: Implementar pantalles de celebració i comparatives divertides quan l'alumne supera un rècord personal (PR) o acumula tonelatge en la sessió (ex: "Has aixecat l'equivalent a 1 cotxe!").
* **Prioritat**: `P2 - High`
* **Esforç**: `3 Story Points (S)`
* **Mòdul**: Gamificació / Entrenament

---

### 🎟️ WIL-5: Portal de Venda "Store Web" / Link-in-Bio per a Entrenadors
* **Descripció**: Permetre a cada entrenador configurar una pàgina pública estil Link-in-Bio on oferir els seus serveis, rutines/dietes individuals o suscripcions amb Stripe integrat i autocreat per als nous clients.
* **Prioritat**: `P2 - High`
* **Esforç**: `8 Story Points (L)`
* **Mòdul**: Monetització / Store

---

### 🎟️ WIL-6: Mòdul de Comunitat i Feed Social Privat
* **Descripció**: Crear un espai de comunitat privada per grup de clients on l'entrenador pugui publicar anuncis, reptes generals i interactuar amb els alumnes.
* **Prioritat**: `P3 - Medium`
* **Esforç**: `5 Story Points (M)`
* **Mòdul**: Comunitat

---

### 🎟️ WIL-7: Automatització de Control de Pagaments i Bloqueig per Impagament
* **Descripció**: Sistema automatitzat que notifica l'alumne en cas de fallada en la targeta i restringeix l'accés als plans d'entrenament si el pagament no es reintenta amb èxit.
* **Prioritat**: `P3 - Medium`
* **Esforç**: `5 Story Points (M)`
* **Mòdul**: Facturació / Admin
