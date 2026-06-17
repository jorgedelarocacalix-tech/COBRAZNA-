# Cobranza App — Contexto del proyecto

Repo: https://github.com/jorgedelarocacalix-tech/COBRAZNA-  
Stack: Vanilla JS · HTML único (index.html) · Supabase · Claude Haiku (Anthropic)  
Supabase project ID: `ixskgawbpwwxdjnkiixt`

---

## Usuarios / PINs

| PIN   | Nombre        | Rol      | Carteras                                      |
|-------|---------------|----------|-----------------------------------------------|
| 7777  | Jorge         | gerente  | Todas (vista global)                          |
| 12345 | Administrador | admin    | Todas + subir PDFs                            |
| 1111  | Equipo Roca   | cobrador | ROCA_COMERCIAL, MOTORS, LIBERTAD, BARRIO      |
| 2222  | Equipo Su Mueble | cobrador | MUEBLE, MOTO, DANLI                       |

El PIN 7777 (gerente) al entrar abre automáticamente el chat IA en modo "Todas las carteras".

---

## Edge Functions Supabase

| Función           | Versión | Descripción                                              |
|-------------------|---------|----------------------------------------------------------|
| ai-recomendaciones | v7     | Análisis del panel IA — recomendaciones por cliente      |
| ai-chat           | v7      | Chat conversacional — modo cartera y modo global         |

---

## Funcionalidades implementadas

### Chat IA (bot)
- Pestaña "Chat" dentro del panel IA (burbuja flotante)
- Registrar promesa, pago, visita, nota vía lenguaje natural
- Confirma siempre: nombre completo + fecha + monto antes de guardar
- Si hay múltiples clientes con el mismo nombre, lista todos y pide aclaración
- Análisis de historial de cliente: "dime más sobre X", "cómo está X", "cuánto debe X"
  - Responde con cuota mensual, saldo, estado de mora, veredicto del perfil
- Preguntas de cartera: "¿quién no ha sido contactado?", "¿quién prometió y no pagó?", etc.
- Chips de preguntas rápidas al abrir el chat (clickeables)
- Acciones via chat guardan en HISTORIAL del cliente (dbSaveHist) + PROMESAS (dbSaveProm)
- Modo "Todas las carteras" (solo rol gerente): consultas cruzadas entre todas las carteras

### Panel de Análisis IA
- **Proyección hasta fin de mes**: semana por semana, total prometido vs meta, promesas vencidas en rojo
- **Alerta extrema**: mora 120d+ con promesa incumplida
- **Urgente**: mora 120d+ sin ningún contacto
- **Cobrar hoy**: vencidos con gestión activa
- **A punto de caer a mora 60d**: vencidos SIN ningún contacto este mes
- **Sin gestión 60-90d**: mora media, nunca contactados
- **Sin promesa 60-90d**: contactados (nota, no contesta) pero sin promesa activa ← nueva
- **Mora 120d+ con gestión**: tienen contacto pero sin promesa
- CONSEJO de Claude al final con números reales de la cartera

### Comentario rápido (💬)
- Enter guarda el comentario sin clic en botón
- El comentario actualiza el estado del cliente en PROMESAS y en el dashboard
- Se guarda en historial_clientes

### UI responsive
- Pantallas ≥1024px (Mac M2 Retina y similares): fuentes más grandes, panel IA más ancho
- Body 16px, KPIs 30px, tablas 14px, panel IA 440px

### Bugs corregidos
- Burbuja IA desaparecía al cambiar de cartera → siempre visible al abrir una cartera
- Modo "Todas las carteras" ahora solo visible para rol gerente
- Clientes mora 120d+ con gestión parcial ya aparecen en el panel de análisis
- Acciones del chat (promesa, pago, visita, nota) ahora guardan en historial del cliente

---

## Estructura de datos clave

### PROMESAS[cartId::nombre]
```js
{ estado, fecha, monto, nota, mora_pendiente, adelantado, cero_prima, ts }
```
Estados: `promesa` | `pago` | `no_contesta` | `visita_agenda` | `nota` | `negociacion`

### historial_clientes (Supabase)
```
cartera_id, cliente_nombre, tipo, monto, nota, fecha_accion, fecha_visita, gestor, created_at
```
Tipos: `promesa` | `pago` | `abono` | `nota` | `visita_agenda` | `visita_realizada` | `promesa_cumplida` | `promesa_incumplida`

### CARTERAS[id]
```js
{ id, empresa, fecha_emision, clientes: [{nombre, saldo, cuota, tramo, dia_pago, ultimo_pago}], load_history }
```

---

## Notas técnicas

- La app es un único `index.html` (~5500 líneas), sin build ni bundler
- Los PDFs de cartera se parsean client-side con pdf.js
- Supabase se usa para persistencia de promesas, historial y snapshots
- El bot usa Claude Haiku via Supabase Edge Functions (Deno/TypeScript)
- Intents del bot detectados por Claude con bloques estructurados: `[ACCION:{...}]` y `[HISTORIAL:{...}]`
- Si una PC no carga la app: revisar DNS (cambiar a 8.8.8.8 / 8.8.4.4 resolvió en una ocasión)
