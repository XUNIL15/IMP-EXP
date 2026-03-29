# Workspace

## Overview

pnpm workspace monorepo using TypeScript. Each package manages its own dependencies.
This is a professional import/export management application called "Transit Pro".

## Stack

- **Monorepo tool**: pnpm workspaces
- **Node.js version**: 24
- **Package manager**: pnpm
- **TypeScript version**: 5.9
- **API framework**: Express 5
- **Database**: PostgreSQL + Drizzle ORM
- **Validation**: Zod (`zod/v4`), `drizzle-zod`
- **API codegen**: Orval (from OpenAPI spec)
- **Build**: esbuild (CJS bundle)
- **Frontend**: React + Vite, Tailwind CSS, Recharts, jsPDF, PapaParse, Lucide React

## Application Features

- **Dashboard**: KPI cards (colis du jour, poids, montant, dettes), graphique évolution 30 jours, top débiteurs
- **Arrivages**: CRUD complet des arrivages (date, nb_colis, poids, coût)
- **Colis**: Gestion des colis avec ID auto-généré (codeReel_JJMMAA), types individuel/mixte, propriétaires
- **Clients**: Gestion des clients avec historique paiements et solde dette
- **Dettes & Paiements**: Suivi des impayés, enregistrement paiements partiels (espèces/virement/chèque/mobile)
- **Bilan journalier**: Rapport par date avec export PDF (jsPDF + autoTable) et CSV (PapaParse)
- **Interface**: 100% en français, sans emoji, icônes Lucide, montants en FCFA

## Structure

```text
artifacts-monorepo/
├── artifacts/              # Deployable applications
│   ├── api-server/         # Express API server
│   └── import-export/      # React + Vite frontend (Transit Pro)
├── lib/                    # Shared libraries
│   ├── api-spec/           # OpenAPI spec + Orval codegen config
│   ├── api-client-react/   # Generated React Query hooks
│   ├── api-zod/            # Generated Zod schemas from OpenAPI
│   └── db/                 # Drizzle ORM schema + DB connection
├── scripts/                # Utility scripts
├── pnpm-workspace.yaml
├── tsconfig.base.json
├── tsconfig.json
└── package.json
```

## Database Schema

- **clients**: id, nom, telephone, adresse, date_creation
- **arrivages**: id, date_arrivee, nb_colis_total, poids_total, cout_total, date_creation
- **colis**: id, arrivage_id, code_colis_reel, code_colis_complet (ex: A109_290326), type, poids, montant, date_creation
- **colis_proprietaires**: id, colis_id, client_id, poids, montant_du, montant_paye, solde
- **paiements**: id, client_id, colis_proprietaire_id, montant, date_paiement, mode

## ID Format for Colis

The package ID is generated as: `{codeColisReel}_{JJMMAA}`
Example: colis "A109" arrived on 29/03/2026 → `A109_290326`

## Packages

### `artifacts/api-server` (`@workspace/api-server`)

Express 5 API server with routes:
- `/api/clients` - CRUD clients + payment history
- `/api/arrivages` - CRUD shipments
- `/api/colis` - CRUD packages with owners
- `/api/colis-proprietaires/:id/paiements` - Record payments
- `/api/dettes` - List outstanding debts
- `/api/dashboard` - Dashboard statistics
- `/api/bilan-journalier` - Daily report

### `artifacts/import-export` (`@workspace/import-export`)

React + Vite frontend at preview path `/`.
Key pages: dashboard, arrivages, colis, clients, dettes, bilan.

### `lib/db` (`@workspace/db`)

Database layer using Drizzle ORM with PostgreSQL.
Run migrations: `pnpm --filter @workspace/db run push`

### `lib/api-spec` (`@workspace/api-spec`)

OpenAPI 3.1 spec. Run codegen: `pnpm --filter @workspace/api-spec run codegen`
