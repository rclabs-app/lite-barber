<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->shop_id) {
            // Injeta silenciosamente no lifecycle do Laravel quem é a barbearia Dona da requisição
            app()->instance('tenant.shop_id', $request->user()->shop_id);
            $request->merge(['shop_id' => $request->user()->shop_id]);
        } else {
            return response()->json(['message' => 'Segurança Ativa: Usuário sem uma barbearia atrelada.'], 403);
        }

        return $next($request);
    }
}
