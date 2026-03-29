import { type ClassValue, clsx } from "clsx";
import { twMerge } from "tailwind-merge";
import { format } from "date-fns";
import { fr } from "date-fns/locale";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatFCFA(amount: number | undefined | null): string {
  if (amount === undefined || amount === null) return "0 FCFA";
  return new Intl.NumberFormat('fr-FR', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount) + ' FCFA';
}

export function formatWeight(weight: number | undefined | null): string {
  if (weight === undefined || weight === null) return "0 kg";
  return new Intl.NumberFormat('fr-FR', {
    style: 'decimal',
    minimumFractionDigits: 1,
    maximumFractionDigits: 2,
  }).format(weight) + ' kg';
}

export function formatDate(dateString: string | undefined | null): string {
  if (!dateString) return "-";
  try {
    return format(new Date(dateString), "dd MMM yyyy", { locale: fr });
  } catch (e) {
    return dateString;
  }
}

export function formatDateForInput(dateString: string | undefined | null): string {
  if (!dateString) return "";
  try {
    return format(new Date(dateString), "yyyy-MM-dd");
  } catch (e) {
    return "";
  }
}
