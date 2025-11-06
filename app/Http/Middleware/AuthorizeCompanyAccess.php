<?php

namespace App\Http\Middleware;

use App\Enums\CompanyType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeCompanyAccess
{
    /**
     * Handle an incoming request.
     * 
     * Vérifie que l'utilisateur authentifié a accès à la société spécifiée dans la route.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Récupérer la company depuis la route (injection automatique de Laravel)
        $company = $request->route('company');
        
        if (!$company) {
            abort(response()->json([
                'error' => 'company_not_found',
            ], 404));
        }
        
        // Vérifier que l'utilisateur a accès à cette société
        $userCompanyIds = $user->companies()->pluck('companies.id')->toArray();
        
        if (!in_array($company->id, $userCompanyIds)) {
            abort(response()->json([
                'error' => 'company_access_denied',
            ], 403));
        }
        
        // Vérifier que la société est de type issuer
        if ($company->type !== CompanyType::ISSUER) {
            abort(response()->json([
                'error' => 'company_must_be_issuer',
            ], 403));
        }
        
        return $next($request);
    }
}
