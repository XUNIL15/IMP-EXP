import { pgTable, serial, date, integer, numeric, timestamp, text } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";
import { transitairesTable } from "./transitaires";

export const arrivagesTable = pgTable("arrivages", {
  id: serial("id").primaryKey(),
  codeArrivage: text("code_arrivage").notNull().unique(),
  transitaireId: integer("transitaire_id").notNull().references(() => transitairesTable.id),
  dateArrivee: date("date_arrivee").notNull(),
  nbColisTotal: integer("nb_colis_total").notNull(),
  poidsTotal: numeric("poids_total", { precision: 10, scale: 2 }).notNull(),
  coutTotal: numeric("cout_total", { precision: 12, scale: 2 }).notNull(),
  dateCreation: timestamp("date_creation").defaultNow().notNull(),
});

export const insertArrivageSchema = createInsertSchema(arrivagesTable).omit({ id: true, dateCreation: true });
export type InsertArrivage = z.infer<typeof insertArrivageSchema>;
export type Arrivage = typeof arrivagesTable.$inferSelect;
