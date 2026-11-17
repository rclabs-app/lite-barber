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
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Não autenticado.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['success' => false, 'message' => 'Usuário inativo.'], 403);
        }

        if (! in_array($user->role, ['owner', 'manager'], true)) {
            return response()->json(['success' => false, 'message' => 'Usuário sem acesso administrativo.'], 403);
        }

        if ($user->shop_id) {
            // Injeta silenciosamente no lifecycle do Laravel quem é a barbearia Dona da requisição
            app()->instance('tenant.shop_id', $user->shop_id);
            $request->merge(['shop_id' => $user->shop_id]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Usuário sem uma barbearia vinculada.',
            ], 403);
        }

        return $next($request);
    }
}
