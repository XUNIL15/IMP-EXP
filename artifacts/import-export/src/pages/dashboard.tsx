import { useGetDashboard } from "@workspace/api-client-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui";
import { formatFCFA, formatWeight } from "@/lib/utils";
import { Package, Scale, TrendingUp, AlertCircle, Loader2 } from "lucide-react";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";

export default function Dashboard() {
  const { data, isLoading, error } = useGetDashboard({});

  if (isLoading) {
    return (
      <div className="flex h-[50vh] items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="p-8 text-center text-red-500 bg-red-50 rounded-xl border border-red-100">
        Une erreur est survenue lors du chargement des données.
      </div>
    );
  }

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-display font-bold text-slate-900">Tableau de bord</h1>
        <p className="text-slate-500 mt-1">Aperçu de votre activité d'aujourd'hui</p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card className="hover:shadow-md transition-shadow">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-sm font-medium text-slate-500 uppercase tracking-wider">Colis (Aujourd'hui)</CardTitle>
            <div className="h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
              <Package className="h-5 w-5 text-blue-600" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-slate-900">{data.today?.nbColis || 0}</div>
          </CardContent>
        </Card>

        <Card className="hover:shadow-md transition-shadow">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-sm font-medium text-slate-500 uppercase tracking-wider">Poids Total</CardTitle>
            <div className="h-10 w-10 bg-emerald-100 rounded-full flex items-center justify-center">
              <Scale className="h-5 w-5 text-emerald-600" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-slate-900">{formatWeight(data.today?.poidsTotal)}</div>
          </CardContent>
        </Card>

        <Card className="hover:shadow-md transition-shadow">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-sm font-medium text-slate-500 uppercase tracking-wider">Montant Total</CardTitle>
            <div className="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
              <TrendingUp className="h-5 w-5 text-indigo-600" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-slate-900">{formatFCFA(data.today?.montantTotal)}</div>
            <p className="text-xs text-slate-500 mt-1">Encaissé: {formatFCFA(data.today?.montantEncaisse)}</p>
          </CardContent>
        </Card>

        <Card className="hover:shadow-md transition-shadow border-red-100">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-sm font-medium text-red-500 uppercase tracking-wider">Dettes Globales</CardTitle>
            <div className="h-10 w-10 bg-red-100 rounded-full flex items-center justify-center">
              <AlertCircle className="h-5 w-5 text-red-600" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-red-600">{formatFCFA(data.totalDettesGlobal)}</div>
            <p className="text-xs text-slate-500 mt-1">Nouveaux impayés du jour: {formatFCFA(data.today?.dettesTotal)}</p>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <Card className="lg:col-span-2 shadow-sm">
          <CardHeader>
            <CardTitle>Évolution des arrivages (Colis)</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-[300px] w-full">
              {data.evolutionColis && data.evolutionColis.length > 0 ? (
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={data.evolutionColis} margin={{ top: 5, right: 20, bottom: 5, left: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                    <XAxis 
                      dataKey="date" 
                      tickFormatter={(val) => new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })} 
                      stroke="#94a3b8" 
                      fontSize={12} 
                      tickMargin={10}
                    />
                    <YAxis stroke="#94a3b8" fontSize={12} tickLine={false} axisLine={false} />
                    <Tooltip 
                      formatter={(value: number) => [`${value} colis`, "Volume"]}
                      labelFormatter={(label) => new Date(label).toLocaleDateString('fr-FR')}
                      contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }}
                    />
                    <Line 
                      type="monotone" 
                      dataKey="nbColis" 
                      stroke="hsl(221, 83%, 53%)" 
                      strokeWidth={3} 
                      dot={{ r: 4, strokeWidth: 2 }} 
                      activeDot={{ r: 6 }} 
                    />
                  </LineChart>
                </ResponsiveContainer>
              ) : (
                <div className="h-full flex items-center justify-center text-slate-400">
                  Pas de données disponibles
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader>
            <CardTitle>Top Débiteurs</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-5">
              {data.topClients && data.topClients.length > 0 ? (
                data.topClients.map((client) => (
                  <div key={client.clientId} className="flex items-center justify-between p-3 rounded-lg bg-slate-50 hover:bg-slate-100 transition-colors">
                    <div className="flex items-center gap-3">
                      <div className="h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-600">
                        {client.clientNom.charAt(0).toUpperCase()}
                      </div>
                      <div>
                        <p className="font-semibold text-slate-900 leading-none">{client.clientNom}</p>
                        <p className="text-xs text-slate-500 mt-1">Dette accumulée</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="font-bold text-red-600">{formatFCFA(Math.abs(client.solde))}</p>
                    </div>
                  </div>
                ))
              ) : (
                <div className="text-center py-8 text-slate-500">
                  Aucun client débiteur
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
