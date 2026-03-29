import React, { useState } from "react";
import { Link, useLocation } from "wouter";
import { cn } from "@/lib/utils";
import {
  LayoutDashboard,
  Package,
  Boxes,
  Users,
  CreditCard,
  FileText,
  Menu,
  X,
  Ship
} from "lucide-react";

const navigation = [
  { name: "Tableau de bord", href: "/", icon: LayoutDashboard },
  { name: "Arrivages", href: "/arrivages", icon: Package },
  { name: "Colis", href: "/colis", icon: Boxes },
  { name: "Clients", href: "/clients", icon: Users },
  { name: "Dettes & Paiements", href: "/dettes", icon: CreditCard },
  { name: "Bilan journalier", href: "/bilan", icon: FileText },
];

export function AppLayout({ children }: { children: React.ReactNode }) {
  const [location] = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <div className="min-h-screen bg-background flex flex-col md:flex-row">
      {/* Mobile Header */}
      <div className="md:hidden flex items-center justify-between p-4 bg-sidebar text-sidebar-foreground">
        <div className="flex items-center gap-2">
          <Ship className="h-6 w-6 text-primary" />
          <span className="font-display font-bold text-lg">Transit Pro</span>
        </div>
        <button onClick={() => setSidebarOpen(true)} className="p-2">
          <Menu className="h-6 w-6" />
        </button>
      </div>

      {/* Sidebar Overlay (Mobile) */}
      {sidebarOpen && (
        <div 
          className="fixed inset-0 z-40 bg-black/50 md:hidden" 
          onClick={() => setSidebarOpen(false)} 
        />
      )}

      {/* Sidebar */}
      <div className={cn(
        "fixed inset-y-0 left-0 z-50 w-72 bg-sidebar text-sidebar-foreground transition-transform duration-300 ease-in-out md:static md:translate-x-0 flex flex-col",
        sidebarOpen ? "translate-x-0" : "-translate-x-full"
      )}>
        <div className="flex items-center justify-between p-6 md:p-8">
          <div className="flex items-center gap-3">
            <div className="bg-primary/20 p-2 rounded-lg">
              <Ship className="h-7 w-7 text-blue-400" />
            </div>
            <div>
              <span className="font-display font-bold text-xl block leading-none text-white">Transit Pro</span>
              <span className="text-xs text-sidebar-foreground/60">Import / Export</span>
            </div>
          </div>
          <button onClick={() => setSidebarOpen(false)} className="md:hidden p-2">
            <X className="h-5 w-5" />
          </button>
        </div>

        <nav className="flex-1 px-4 space-y-1 overflow-y-auto">
          {navigation.map((item) => {
            const isActive = location === item.href;
            return (
              <Link key={item.name} href={item.href}>
                <div
                  onClick={() => setSidebarOpen(false)}
                  className={cn(
                    "flex items-center gap-3 px-4 py-3.5 rounded-xl cursor-pointer transition-all duration-200 group",
                    isActive 
                      ? "bg-primary text-primary-foreground font-medium shadow-md shadow-primary/20" 
                      : "text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-white"
                  )}
                >
                  <item.icon className={cn("h-5 w-5 transition-transform duration-200", isActive ? "scale-110" : "group-hover:scale-110")} />
                  {item.name}
                </div>
              </Link>
            );
          })}
        </nav>
        
        <div className="p-6">
          <div className="bg-sidebar-accent/50 p-4 rounded-xl text-xs text-sidebar-foreground/50 text-center border border-white/5">
            Système de gestion<br/>© 2025 Transit Pro
          </div>
        </div>
      </div>

      {/* Main Content */}
      <main className="flex-1 overflow-x-hidden p-4 md:p-8 w-full animate-fade-in">
        <div className="max-w-7xl mx-auto">
          {children}
        </div>
      </main>
    </div>
  );
}
