<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\UserMKS;
use App\Models\UserPYC;
use App\Models\Articulo;
use App\Models\Proveedor;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class ArticuloController extends Controller
{
    public function getPreciosXSucursal(Request $request){
        $sucursal = $request->input('suc','001');
        $art = $request->input('art','0');
        $proveedor = $request->input('prov','0');
        
        $arti = DB::table('inviar')->where('art', $art)->first();
        if (is_null($arti)) {
            return response()->json(array(
                'code'      =>  422,
                'message'   =>  'El código del artículo no fue encontrado'
            ), 422);  
        }
        $articulo = DB::table('invart')
                    ->leftJoin('inviar', 'invart.art', '=', 'inviar.art')
                    ->select('invart.art','des1','precio_vta0','precio_vta1'
                            ,'precio_vta2','precio_vta3','precio_vta4',
                            'cant_pre0', 'cant_pre1', 'cant_pre2', 'cant_pre3',
                             'cant_pre4', 'cve_pro')
                    ->where('alm',$sucursal)
                    ->where('invart.art',$art);
        
        $existeEnSuc = $articulo->first();

        //return response()->json($existeEnSuc);
        if(is_null($existeEnSuc)){
            return response()->json(array(
                'code'      =>  422,
                'message'   =>  'El código capturado no existe en la sucursal seleccionada o está inactivo'
            ), 422);  
        }

        $esSuProv = $articulo->where('cve_pro', $proveedor)->first();

        if(is_null($esSuProv)){
            return response()->json(array(
                'code'      =>  422,
                'message'   =>  'El código del artículo no corresponde al proveedor seleccionado'
            ), 422);  
        }
        
        return response()->json($esSuProv);

    }

    public function apiLogin(Request $request){
        //Validando si es el usuario ADMIN
        //En caso de serlo, no se valida en tabla de MKS
        if(strtoupper(trim($request->usuario)) === 'ADMIN'){
            $user = UserPYC::where('user_mks','ADMIN')->first();
            if (! Hash::check($request->clave, $user->password)) {
                return response()->json(array(
                    'code'      =>  422,
                    'message'   =>  'Contraseña incorrecta',
                    'error'     =>  'Contraseña incorrecta',
                ), 403);  
            }

            $array = array('nombre' => $user->name, 
                'usuario' => $user->user_mks,
                'token' => $user->createToken($user->user_mks)->plainTextToken
              );
            return response()->json($array);
        }

        //Si no es el usuario admin, valida primero que exista en MKS

        $user = UserMKS::where('nombre', $request->usuario)->first();
        if($user == null){
            return response()->json(array(
                'code'      =>  422,
                'message'   =>  'No se encontró el usuario',
                'error'     =>  'No se encontró el usuario',
            ), 403);
        }

        //Si existe en MKS, pero no en tablas PYC, no puede usar el sistema
        $userPYC = UserPYC::where('user_mks', $request->usuario)->first();
        if($userPYC == null){
            return response()->json(array(
                'code'      =>  422,
                'message'   =>  'Usuario MKS no dado de alta en MABPYC',
                'error'     =>  'No se encontró el usuario en MABPYC',
            ), 403);
        }
 
        if ($request->clave !== $user->pwd)
         {
            /*throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);*/
            return response()->json(array(
                'code'      =>  422,
                'message'   =>  'Contraseña incorrecta',
                'error'     =>  'Contraseña incorrecta',
            ), 403);  
        }

        $array = array('nombre' => $user->name, 
                'usuario' => $user->user_mks,
                'token' => $user->createToken($request->user_mks)->plainTextToken
              );
        return response()->json($array);
    }

    public function getProveedores(Request $request)
    {
        $comprador = $request->input('compr',-1);
        $proveedores = DB::table('cprprv')
                            ->select('proveedor', 'nom')
                            ->where('modulo','P')
                            ->where('comprador',$comprador)
                            ->get();
        return response()->json($proveedores);
    }


    public function getAcuerdos(Request $request){
        $comprador = $request->input('compr',-1);
        $proveedor = $request->input('prov','00');
        /*$apoyos = DB::table('Rca_ApoyosDir')
                        ->select(DB::raw('Folio + \'*\' as Folio,
                        Comprador, Nombre, Linea1, \'APOYOS DIRECCION\' as boletin, fecApoyo as Fecha'))
                        ->where('Comprador',$comprador);
                        */

        $acuerdos = DB::table('Rca_Acuerdos')
                        ->select(DB::raw('Folio, Comprador, Nombre, Linea1, boletin, Fecha'))
                        //->union($apoyos)
                        ->where('Comprador',$comprador)
                        ->where('Clave', $proveedor)
                        ->get();
        return response()->json($acuerdos);
    }

}
