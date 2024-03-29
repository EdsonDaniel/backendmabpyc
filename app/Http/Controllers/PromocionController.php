<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserMKS;
use App\Models\UserPYC;
use App\Models\PromocionPYC;
use App\Models\PromocionSucPYC;
use App\Models\PromocionDetPYC;
use App\Models\Articulo;
use App\Models\Proveedor;
use App\Models\PromocionMKS;
use App\Models\PromocionDetMKS;
use Illuminate\Support\Facades\DB;

class PromocionController extends Controller
{
    public function crearPrePromocion(Request $request){
        $datos = $request->input('datos',[]);
        $sucursales = $datos['sucSelected'];
        $articulos = $datos['arts'];

        //return response()->json($datos);

        if(is_null($sucursales) || count($sucursales) < 1 ||
           is_null($articulos) || count($articulos) < 1){
            return response()->json(array(
                'code'      =>  421,
                'message'   =>  'No se seleccionó ninguna sucursal',
                'error'     =>  'No se seleccionó ninguna sucursal',
            ), 421);
        }

        //Insertando la cabecera
        DB::beginTransaction();
        $promocion_pyc = new PromocionPYC;
        $promocion_pyc->status = -1;
        $promocion_pyc->desProm = $datos['nombre'];
        $promocion_pyc->fec_ini = $datos['fec_ini'];
        $promocion_pyc->fec_fin = $datos['fec_fin'];
        $promocion_pyc->hra_ini = '010000';
        $promocion_pyc->hra_fin = '235959';
        $promocion_pyc->inc_similares = is_null($datos['aplicaSim']) ? 'N' : 'S' ;
        $promocion_pyc->tpoProm = $datos['tipo'];
        
        $promocion_pyc->cte = is_null($datos['cliente']) ? '         ' : 
        str_pad(strval($datos['cliente']), 9, "0", STR_PAD_LEFT);;
        
        $promocion_pyc->retail = $datos['retail'];
        $promocion_pyc->mostrador = $datos['mostrador'];
        $promocion_pyc->con_pag = is_null($datos['condPago']) ? '     ' 
        : $datos['condPago'];
        $promocion_pyc->seg_0 = is_null($datos['seg1']) ? '   ' 
        : $datos['seg1'];
        $promocion_pyc->seg_1 = is_null($datos['seg2']) ? '   ' 
        : $datos['seg2'];
        $promocion_pyc->seg_2 = is_null($datos['seg3']) ? '   ' 
        : $datos['seg3'];
        $promocion_pyc->seg_3 = is_null($datos['seg4']) ? '   ' 
        : $datos['seg4'];
        $promocion_pyc->seg_4 = '   ';
        
        $promocion_pyc->uds_limite = is_null($datos['limPzs']) ?
        0 : $datos['limPzs'];
        $promocion_pyc->uds_por_cte = is_null($datos['udsVenta']) ? 
        0 : $datos['udsVenta'];
        $promocion_pyc->cantidad_minima = is_null($datos['cantMin']) ?
        0 : $datos['cantMin'] ;
        $promocion_pyc->compra_minima = is_null($datos['montoMin']) ?
        0 : $datos['montoMin'];
        $promocion_pyc->u_alt = $datos['u_alta'];
        $promocion_pyc->proveedor = $datos['proveedor'];
        $promocion_pyc->uds_por_cte = is_null($datos['udsVenta']) ?
        0 : $datos['udsVenta'];
        $promocion_pyc->uds_vendidas = 0;
        $promocion_pyc->paga = $datos['paga'];
        $promocion_pyc->folio_ac = $datos['folioAcuerdo'];
        $promocion_pyc->boletin = $datos['boletin'];
        $promocion_pyc->suc_prec_base = $datos['precBase'];

        try{
            $promocion_pyc->save();
        }catch(Throwable $e){
            DB::rollBack();
            return response()->json(array(
                'code'      =>  421,
                'message'   =>  'Ocurrió un error al guardar, intentelo nuevamente',
                'error'     =>  'Ocurrió un error al guardar, intentelo nuevamente',
            ), 421);
        }
        

        foreach ($sucursales as $suc) {
            $sucSelected = new PromocionSucPYC;
            $sucSelected->prm_id = $promocion_pyc->id;
            $sucSelected->suc = $suc;
            $sucSelected->save();
            //return response()->json($sucSelected);
        }

        //Insertando el detalle
        foreach ($articulos as $art => $value) {
            $a = DB::table('invart')
                    ->where('art', $value['cve'])
                    ->where('alm',$datos['precBase'])
                    ->first();
                    //return response()->json($sucSelected);
            $prmdet = new PromocionDetPYC;
            $prmdet->id_pyc_prom = $promocion_pyc->id;
            $prmdet->status = 1;
            $prmdet->cve_art = $value['cve'];
            $prmdet->des_art = $value['des1'];
            
            //array_key_exists(array_key, array_name)
            
            $prmdet->p_dsc_0 = 0.0;
            $prmdet->p_dsc_1 = 0.0;
            $prmdet->p_dsc_2 = 0.0;
            $prmdet->Monto_Dsc = 0.0;

            //Si es promocion de precio
            if($datos['tipo'] == 1){
                //Si precio no esta capturado ponemos el del cat art
                $prmdet->precio_0 = is_null($value['precio1']) ? $a->precio_vta0 : $value['precio1'];
                $prmdet->precio_1 = is_null($value['precio2']) ? $a->precio_vta1 : $value['precio2'];
                $prmdet->precio_2 = is_null($value['precio3']) ? $a->precio_vta2 : $value['precio3'];
                $prmdet->precio_3 = is_null($value['precio4']) ? $a->precio_vta3 : $value['precio4'];
                $prmdet->precio_4 = is_null($value['precio5']) ? $a->precio_vta4 : $value['precio5'];
                
                $prmdet->sin_cargo = 'N';
                $prmdet->cobradas = 0.0;
                $prmdet->regaladas = 0.0;
                //$prmdet->art_reg = $value->cve;
                //$prmdet->emp_reg = $value->cve;
                $prmdet->fac_min_reg = 0.0;
                $prmdet->precio_reg = 0.0;
            }

            //Si es promocion de Regalo
            else if($datos['tipo'] == 5){
                //$prmdet->cve_art = $value['cod_cob'];
                //$prmdet->des_art = $value['desc_cob'];
                $prmdet->sin_cargo = 'S';
                $prmdet->cobradas = $value['cobradas'];
                $prmdet->regaladas = $value['regaladas'];
                $prmdet->art_reg = $value['cod_reg'];
                $prmdet->emp_reg = $value['emp_reg'];
                $prmdet->fac_min_reg = $value['fac_min_reg'];
                $prmdet->precio_reg = 0.0;
                $prmdet->desc_reg = $value['desc_reg'];

                //Si precio no esta capturado ponemos el del cat art
                $prmdet->precio_0 = $a->precio_vta0;
                $prmdet->precio_1 = $a->precio_vta1;
                $prmdet->precio_2 = $a->precio_vta2;
                $prmdet->precio_3 = $a->precio_vta3;
                $prmdet->precio_4 = $a->precio_vta4;
            }
            
            $prmdet->save();
        }

        DB::commit();

        
        return response()->json($promocion_pyc);
    }

    public function getPromocionesXComprador(Request $request){
        $comprador = $request->input('compr', -1);
        $promociones = PromocionPYC::where('u_alt',$comprador)
                        ->leftJoin('cprprv', 'pyc_prmhdr.proveedor','=','cprprv.proveedor')
                        ->select('pyc_prmhdr.*','cprprv.nom as nom_prov')
                        ->orderByDesc('updated_at')
                        ->get()
                        ->toArray();
        return response()->json($promociones);
    }

    public function getAllPromociones(Request $request){
        $comprador = $request->input('compr', -1);
        $promociones = PromocionPYC::
        //where('u_alt',$comprador)
                        leftJoin('cprprv', 'pyc_prmhdr.proveedor','=','cprprv.proveedor')
                        ->select('pyc_prmhdr.*','cprprv.nom as nom_prov')
                        ->orderByDesc('updated_at')
                        ->get()
                        ->toArray();
        return response()->json($promociones);
    }

    public function getPromAut(Request $request){
        $comprador = $request->input('compr', -1);
        $promociones = PromocionPYC::
                        whereIn('status', [-1,0])
                        ->rightJoin('cprprv', 'pyc_prmhdr.proveedor','=','cprprv.proveedor')
                        ->select('pyc_prmhdr.*','cprprv.nom as nom_prov')
                        ->orderByDesc('updated_at')
                        ->get()
                        ->toArray();
        return response()->json($promociones);
    }

    public function getDetallePromocion(Request $request){
        $idprom = $request->input("idprom","-1");
        $comprador = $request->input("compr","-1");
        $promo = PromocionPYC::where('id',$idprom)
                    ->leftJoin('cprprv', 'pyc_prmhdr.proveedor','=','cprprv.proveedor')
                    ->select('pyc_prmhdr.*','cprprv.nom as nom_prov')
                    ->get()->first()
                    ->toArray();
        $detprom = PromocionDetPYC::where('id_pyc_prom',$idprom)
                    ->get()->toArray();
        $suc = PromocionSucPYC::where('prm_id',$idprom)->select('suc')->get()->toArray();

        //Agregando factor de empaques
        foreach ($detprom as $key => $value) {
            //Buscando el articulo en tabla invart
            $factores = DB::table('invart')
                    ->where('art', $value['cve_art'])
                    ->where('alm', $promo['suc_prec_base'])
                    ->select('cant_pre0', 'cant_pre1', 'cant_pre2', 
                        'cant_pre3', 'cant_pre4', 'precio_vta0', 'precio_vta1',
                        'precio_vta2', 'precio_vta3', 'precio_vta4'
                    )
                    ->get()
                    ->first();
            $detprom[$key]['cant_pre0'] = $factores->cant_pre0;
            $detprom[$key]['cant_pre1'] = $factores->cant_pre1;
            $detprom[$key]['cant_pre2'] = $factores->cant_pre2;
            $detprom[$key]['cant_pre3'] = $factores->cant_pre3;
            $detprom[$key]['cant_pre4'] = $factores->cant_pre4;

            $detprom[$key]['precio_vta0'] = $factores->precio_vta0;
            $detprom[$key]['precio_vta1'] = $factores->precio_vta1;
            $detprom[$key]['precio_vta2'] = $factores->precio_vta2;
            $detprom[$key]['precio_vta3'] = $factores->precio_vta3;
            $detprom[$key]['precio_vta4'] = $factores->precio_vta4;
        }

        $datos = array('prom' => $promo, 'arts' => $detprom, 'suc' => $suc );
        return response()->json($datos);
    }


    public function editarPrePromocion(Request $request){
        $idprom = $request->input('idprom',"0");
        $datos = $request->input('datos',[]);
        $sucursales = $datos['sucSelected'];
        $articulos = $datos['arts'];

        //return response()->json($datos['arts']);

        if(is_null($sucursales) || count($sucursales) < 1 ||
           is_null($articulos) || count($articulos) < 1){
            return response()->json(array(
                'code'      =>  421,
                'message'   =>  'No se seleccionó ninguna sucursal o ningún artículo',
                'error'     =>  'No se seleccionó ninguna sucursal o ningún artículo',
            ), 421);
        }

        //Recuperando la cabecera
        $promocion_pyc = PromocionPYC::where('id',$idprom)->first();


        //Actualizando la cabecera
        //$promocion_pyc = new PromocionPYC;
        $promocion_pyc->status = -1;
        $promocion_pyc->desProm = $datos['nombre'];
        $promocion_pyc->fec_ini = $datos['fec_ini'];
        $promocion_pyc->fec_fin = $datos['fec_fin'];
        $promocion_pyc->hra_ini = '010000';
        $promocion_pyc->hra_fin = '235959';
        $promocion_pyc->inc_similares = is_null($datos['aplicaSim']) ? 'N' : 'S' ;
        $promocion_pyc->tpoProm = $datos['tipo'];
        $promocion_pyc->cte = is_null($datos['cliente']) ? '         ' : 
        str_pad(strval($datos['cliente']), 9, "0", STR_PAD_LEFT);;
        
        $promocion_pyc->retail = $datos['retail'];
        $promocion_pyc->mostrador = $datos['mostrador'];
        $promocion_pyc->con_pag = is_null($datos['condPago']) ? '     ' 
        : $datos['condPago'];
        $promocion_pyc->seg_0 = is_null($datos['seg1']) ? '   ' 
        : $datos['seg1'];
        $promocion_pyc->seg_1 = is_null($datos['seg2']) ? '   ' 
        : $datos['seg2'];
        $promocion_pyc->seg_2 = is_null($datos['seg3']) ? '   ' 
        : $datos['seg3'];
        $promocion_pyc->seg_3 = is_null($datos['seg4']) ? '   ' 
        : $datos['seg4'];
        $promocion_pyc->seg_4 = '   ';
        
        $promocion_pyc->uds_limite = is_null($datos['limPzs']) ?
        0 : $datos['limPzs'];
        $promocion_pyc->uds_por_cte = is_null($datos['udsVenta']) ? 
        0 : $datos['udsVenta'];
        $promocion_pyc->cantidad_minima = is_null($datos['cantMin']) ?
        0 : $datos['cantMin'] ;
        $promocion_pyc->compra_minima = is_null($datos['montoMin']) ?
        0 : $datos['montoMin'];
        $promocion_pyc->u_alt = $datos['u_alta'];
        $promocion_pyc->proveedor = $datos['proveedor'];
        $promocion_pyc->uds_por_cte = is_null($datos['udsVenta']) ?
        0 : $datos['udsVenta'];
        $promocion_pyc->uds_vendidas = 0;
        $promocion_pyc->paga = $datos['paga'];
        $promocion_pyc->folio_ac = $datos['folioAcuerdo'];
        $promocion_pyc->boletin = $datos['boletin'];
        $promocion_pyc->suc_prec_base = $datos['precBase'];

        DB::beginTransaction();
        try{
            $promocion_pyc->save();
        }catch(Throwable $e){
            DB::rollBack();
            return response()->json(array(
                'code'      =>  421,
                'message'   =>  'Ocurrió un error al guardar, intentelo nuevamente',
                'error'     =>  'Ocurrió un error al guardar, intentelo nuevamente',
            ), 421);
        }
        

        //Actualizando las tablas de sucursales
        try{
            $eliminadas = PromocionSucPYC::where('prm_id', $idprom)->delete();
        }catch(Throwable $e){
            DB::rollBack();
            return response()->json(array(
                'code'      =>  421,
                'message'   =>  'Ocurrió un error al guardar, intentelo nuevamente',
                'error'     =>  'Ocurrió un error al guardar, intentelo nuevamente',
            ), 421);
        }

        foreach ($sucursales as $suc) {
            $sucSelected = new PromocionSucPYC;
            $sucSelected->prm_id = $promocion_pyc->id;
            $sucSelected->suc = $suc;
            $sucSelected->save();
            //return response()->json($sucSelected);
        }

        //Actualizando el detalle
         try{
            $eliminadas = PromocionDetPYC::where('id_pyc_prom', $idprom)->delete();
        }catch(Throwable $e){
            DB::rollBack();
            return response()->json(array(
                'code'      =>  421,
                'message'   =>  'Ocurrió un error al guardar, intentelo nuevamente',
                'error'     =>  'Ocurrió un error al guardar, intentelo nuevamente',
            ), 421);
        }

        foreach ($articulos as $art => $value) {
            $a = DB::table('invart')
                    ->where('art', $value['cve'])
                    ->where('alm',$datos['precBase'])
                    ->first();
                    //return response()->json($sucSelected);
            $prmdet = new PromocionDetPYC;
            $prmdet->id_pyc_prom = $promocion_pyc->id;
            $prmdet->status = 1;
            $prmdet->cve_art = $value['cve'];
            $prmdet->des_art = $value['des1'];
            //$prmdet->sin_cargo = $value->cve;
            //$prmdet->cobradas = $value->cve;
            //$prmdet->regaladas = $value->cve;
            //$prmdet->art_reg = $value->cve;
            //$prmdet->emp_reg = $value->cve;
            //$prmdet->fac_min_reg = $value->cve;
            //$prmdet->precio_reg = $value->cve;

            //Si precio no esta capturado ponemos el del cat art
            $prmdet->precio_0 = !array_key_exists('precio1',$value) ? $a->precio_vta0 : $value['precio1'];
            $prmdet->precio_1 = !array_key_exists('precio2',$value) ? $a->precio_vta1 : $value['precio2'];
            $prmdet->precio_2 = !array_key_exists('precio3',$value) ? $a->precio_vta2 : $value['precio3'];
            $prmdet->precio_3 = !array_key_exists('precio4',$value) ? $a->precio_vta3 : $value['precio4'];
            $prmdet->precio_4 = !array_key_exists('precio5',$value) ? $a->precio_vta4 : $value['precio5'];
            $prmdet->p_dsc_0 = 0.0;
            $prmdet->p_dsc_1 = 0.0;
            $prmdet->p_dsc_2 = 0.0;
            $prmdet->Monto_Dsc = 0.0;

            //Si es promocion de precio
            if($datos['tipo'] == 1){
                $prmdet->sin_cargo = 'N';
                $prmdet->cobradas = 0.0;
                $prmdet->regaladas = 0.0;
                //$prmdet->art_reg = $value->cve;
                //$prmdet->emp_reg = $value->cve;
                $prmdet->fac_min_reg = 0.0;
                $prmdet->precio_reg = 0.0;
            } else{
                $prmdet->sin_cargo = 'S';
                $prmdet->cobradas = $value['cobradas'];
                $prmdet->regaladas = $value['regaladas'];;
                $prmdet->art_reg = $value['cod_reg'];
                $prmdet->emp_reg = $value['emp_reg'];
                $prmdet->fac_min_reg = $value['fac_min_reg'];
                $prmdet->precio_reg = 0.0;
                $prmdet->desc_reg = $value['desc_reg'];
            }


            $prmdet->save();


        }

        DB::commit();

        
        return response()->json($promocion_pyc);
    }

    public function softDeletePromocion(Request $request){
        $idprom = $request->input('idprom',"0");
        //Recuperando la cabecera
        $promocion_pyc = PromocionPYC::where('id',$idprom)->first();

        $promocion_pyc->status = "-2";
        $promocion_pyc->save();

        return response()->json($promocion_pyc);
    }



    //Autorizar
    public function getDetallePromocionAut(Request $request){
        $idprom = $request->input("idprom","-1");
        $comprador = $request->input("compr","-1");
        $usuario = UserPYC::where('cve_corta', $comprador)->first();

        $permiso = DB::table('pyc_roles_permisos')
                            ->where('rol_id',$usuario->id)
                            ->first();
        if(is_null($permiso)){
            return 'es lamentable';
        }

        $promo = PromocionPYC::where('id',$idprom)->first();
        if($promo->status == -1){
            $promo->status = 0;
            $promo->save();
        }
        
        $promo = PromocionPYC::where('id',$idprom)
                    ->leftJoin('cprprv', 'pyc_prmhdr.proveedor','=','cprprv.proveedor')
                    ->select('pyc_prmhdr.*','cprprv.nom as nom_prov')
                    ->get()->first()
                    ->toArray();
        $detprom = PromocionDetPYC::where('id_pyc_prom',$idprom)
                    ->get()->toArray();
        $suc = PromocionSucPYC::where('prm_id',$idprom)->select('suc')->get()->toArray();

        //Agregando factor de empaques
        foreach ($detprom as $key => $value) {
            //Buscando el articulo en tabla invart
            $factores = DB::table('invart')
                    ->where('art', $value['cve_art'])
                    ->where('alm', $promo['suc_prec_base'])
                    ->select('cant_pre0', 'cant_pre1', 'cant_pre2', 
                        'cant_pre3', 'cant_pre4', 'precio_vta0', 'precio_vta1',
                        'precio_vta2', 'precio_vta3', 'precio_vta4'
                    )
                    ->get()
                    ->first();
            $detprom[$key]['cant_pre0'] = $factores->cant_pre0;
            $detprom[$key]['cant_pre1'] = $factores->cant_pre1;
            $detprom[$key]['cant_pre2'] = $factores->cant_pre2;
            $detprom[$key]['cant_pre3'] = $factores->cant_pre3;
            $detprom[$key]['cant_pre4'] = $factores->cant_pre4;

            $detprom[$key]['precio_vta0'] = $factores->precio_vta0;
            $detprom[$key]['precio_vta1'] = $factores->precio_vta1;
            $detprom[$key]['precio_vta2'] = $factores->precio_vta2;
            $detprom[$key]['precio_vta3'] = $factores->precio_vta3;
            $detprom[$key]['precio_vta4'] = $factores->precio_vta4;
        }

        $datos = array('prom' => $promo, 'arts' => $detprom, 'suc' => $suc );
        return response()->json($datos);
    }

    public function creaPromoMks(Request $request)
    {

        $idprom = $request->input("idprom","-1");
        $comprador = $request->input("compr","-1");
        $usuario = UserPYC::where('cve_corta', $comprador)->first();

        //return response()->json($comprador);

        if(is_null($usuario)){
            try{
                $promo->save();
                ;
            }catch(Throwable $e){
                DB::rollBack();
                return response()->json(array(
                    'code'      =>  421,
                    'message'   =>  'Usuario no encontrado',
                    'error'     =>  'Usuario no encontrado',
                ), 421);
            }
        }

        $permiso = DB::table('pyc_roles_permisos')
                            ->where('rol_id',$usuario->id)
                            ->first();
        if(is_null($permiso)){
            try{
                $promo->save();
                ;
            }catch(Throwable $e){
                DB::rollBack();
                return response()->json(array(
                    'code'      =>  421,
                    'message'   =>  'Usuario sin permisos para dar de alta la promoción',
                    'error'     =>  'Usuario sin permisos para dar de alta la promoción',
                ), 421);
            }
        }

        $consecutivo = DB::table('prmhdr')
             ->select(DB::raw('isnull(MAX( substring(NumProm,2,7)),0)+1 as numProm'))
             ->where('modulo', 'P')
             ->first();
             //->get();

        $consec_aplicar = strval($consecutivo->numProm);
        $size_actual = strlen($consec_aplicar);
        for ($i= $size_actual; $i < 7; $i++) { 
            $consec_aplicar = '0'.$consec_aplicar;
        }
        $consec_aplicar = 'P'.$consec_aplicar;
        //return response()->json($consec_aplicar);
        DB::beginTransaction();

        $promocion_pyc = PromocionPYC::where('id',$idprom)->first();
        $sucursales = PromocionSucPYC::where('prm_id', $idprom)->get()->toArray();
        $dat = '';
        $articulos = PromocionDetPYC::where('id_pyc_prom',$idprom)->get();

        //Insertando registros en prmhdr por cada sucursal
        foreach ($sucursales as $key => $value) {
            $dat.= $value['suc'].$consec_aplicar.' , ';
            $promo = new PromocionMKS;
            $promo->ibuff = '     ';
            $promo->cia = 'MAB';
            $promo->alm = $value['suc'];
            $promo->suc = $value['suc'];
            $promo->NumProm = $consec_aplicar;
            $promo->DesProm = $promocion_pyc->desProm;
            $promo->fec_ini = $promocion_pyc->fec_ini;
            $promo->fec_fin = $promocion_pyc->fec_fin;
            $promo->hra_ini = $promocion_pyc->hra_ini;
            $promo->hra_fin = $promocion_pyc->hra_fin;
            $promo->hra_ini = $promocion_pyc->hra_ini;
            $promo->Modulo = 'P';
            $promo->status = '1';
            $promo->inc_similares = $promocion_pyc->inc_similares;
            $promo->AplicaSobrePrm = ' ';
            $promo->AplicaSobreNeg = '1';
            $promo->SelPor = '0';
            $promo->TpoProm = $promocion_pyc->tpoProm;
            //$promo->cte = $promocion_pyc->
            $promo->cte = $promocion_pyc->cte;
            $promo->CodBarCF = '                ';
            $promo->dep_sur = '      ';
            $promo->con_pag = $promocion_pyc->con_pag;
            $promo->seg_0 = $promocion_pyc->seg_0;
            $promo->seg_1 = $promocion_pyc->seg_1;
            $promo->seg_2 = $promocion_pyc->seg_2;
            $promo->seg_3 = $promocion_pyc->seg_3;
            $promo->seg_4 = '   ';
            $promo->giro_0 = '   ';
            $promo->giro_1 = '   ';
            $promo->giro_2 = '   ';
            $promo->giro_3 = '   ';
            $promo->giro_4 = '   ';
            $promo->usa_limite = 'N';
            $promo->uds_limite = $promocion_pyc->uds_limite;
            $promo->uds_vendidas = $promocion_pyc->uds_vendidas;
            $promo->uds_por_cte = $promocion_pyc->uds_por_cte;
            $promo->cantidad_minima = $promocion_pyc->cantidad_minima;
            $promo->compra_minima = $promocion_pyc->compra_minima;
            $promo->f_alt = date("Ymd");
            $promo->h_alt = date("His");
            $promo->u_alt = $promocion_pyc->u_alt;
            $promo->f_mod = date("Ymd");
            $promo->h_mod = date("His");
            $promo->u_mod = $promocion_pyc->u_alt;

            try{
                $promo->save();
                ;
            }catch(Throwable $e){
                DB::rollBack();
                return response()->json(array(
                    'code'      =>  421,
                    'message'   =>  'Ocurrió un error al guardar, intentelo nuevamente',
                    'error'     =>  'Ocurrió un error al guardar, intentelo nuevamente',
                ), 421);
            }

            //Insertando el detalle
            //Insertando registros en prmhdet por cada articulo
            $npar = 0;
            foreach ($articulos as $key => $value2) {
                $det_prom = new PromocionDetMKS;
                $det_prom->ibuff = '     ';
                $det_prom->cia = 'MAB';
                $det_prom->alm = $value['suc'];
                $det_prom->suc = $value['suc'];
                $det_prom->NumProm = $consec_aplicar;
                $det_prom->NPar = str_pad(strval($npar), 5, " ", STR_PAD_LEFT);
                $det_prom->RenExcep = ' ';
                $det_prom->status = 1;
                $det_prom->cve_art = $value2['cve_art'];
                $det_prom->des_art = $value2['des_art'];
                $det_prom->lin = '    ';
                $det_prom->s_lin = '    ';
                $det_prom->fam = '    ';
                $det_prom->s_fam = '    ';
                $det_prom->marca = '        ';
                $det_prom->temp = '    ';
                $det_prom->prv = '         ';
                $det_prom->Id_modelo = '                    ';
                $det_prom->cte = '         ';
                $det_prom->seg = '   ';
                $det_prom->giro = '   ';
                $det_prom->sin_cargo = $value2['sin_cargo'];
                $det_prom->cobradas = $value2['cobradas'];
                $det_prom->regaladas = $value2['regaladas'];
                $det_prom->art_reg = str_pad(strval($value2['art_reg']), 10);
                $det_prom->emp_reg = str_pad(strval($value2['emp_reg']), 3, " ", STR_PAD_LEFT);
                $det_prom->fac_min_reg = $value2['fac_min_reg'];
                $det_prom->precio_reg = $value2['precio_reg'];
                $det_prom->precio_0 = $value2['precio_0'];
                $det_prom->precio_1 = $value2['precio_1'];
                $det_prom->precio_2 = $value2['precio_2'];
                $det_prom->precio_3 = $value2['precio_3'];
                $det_prom->precio_4 = $value2['precio_4'];
                $det_prom->p_dsc_0 = $value2['p_dsc_0'];
                $det_prom->p_dsc_1 = $value2['p_dsc_1'];
                $det_prom->p_dsc_2 = $value2['p_dsc_2'];
                $det_prom->MontoDsc = $value2['Monto_Dsc'];
                $det_prom->PuntosSuma = $value2['Monto_Dsc'];
                $det_prom->PuntosResta = $value2['Monto_Dsc'];
                $det_prom->PorcMonedero = $value2['Monto_Dsc'];
                $det_prom->MontoBoletos = $value2['Monto_Dsc'];
                $det_prom->Boletos = 0;

                try{
                    $det_prom->save();
                    ;
                }catch(Throwable $e){
                    DB::rollBack();
                    return response()->json(array(
                        'code'      =>  421,
                        'message'   =>  'Ocurrió un error al guardar, intentelo nuevamente',
                        'error'     =>  'Ocurrió un error al guardar, intentelo nuevamente',
                    ), 421);
                }
                $npar = $npar +1;
                
                //return response()->json($det_prom->art_reg);
                //return response()->json($value['art_reg']);
            }


        }

        //Hasta aqui todo bien, falta validar que el cliente exista
        $promocion_pyc->numProm = $consec_aplicar;
        $promocion_pyc->autoriza = $comprador;
        $promocion_pyc->status = 1;
        try{
            $promocion_pyc->save();
        }catch(Throwable $e){
            DB::rollBack();
            return response()->json(array(
                'code'      =>  421,
                'message'   =>  'Ocurrió un error al guardar, intentelo nuevamente',
                'error'     =>  'Ocurrió un error al guardar, intentelo nuevamente',
            ), 421);
        }


        DB::commit();
        return response()->json($promocion_pyc);
        //return response()->json($consecutivo->numProm);
    }

    public function denegarProm(Request $request){
        $idprom = $request->input("idprom","-1");
        $comprador = $request->input("compr","-1");
        $usuario = UserPYC::where('cve_corta', $comprador)->first();

        //return response()->json($comprador);

        if(is_null($usuario)){
            try{
                $promo->save();
                ;
            }catch(Throwable $e){
                DB::rollBack();
                return response()->json(array(
                    'code'      =>  421,
                    'message'   =>  'Usuario no encontrado',
                    'error'     =>  'Usuario no encontrado',
                ), 421);
            }
        }

        $permiso = DB::table('pyc_roles_permisos')
                            ->where('rol_id',$usuario->id)
                            ->first();
        if(is_null($permiso)){
            try{
                $promo->save();
                ;
            }catch(Throwable $e){
                DB::rollBack();
                return response()->json(array(
                    'code'      =>  421,
                    'message'   =>  'Usuario sin permisos para denegar la promoción',
                    'error'     =>  'Usuario sin permisos para denegar la promoción',
                ), 421);
            }
        }

        $promocion_pyc = PromocionPYC::where('id',$idprom)->first();
        $promocion_pyc->status = 2;
        try{
            $promocion_pyc->save();
        }catch(Throwable $e){
            DB::rollBack();
            return response()->json(array(
                'code'      =>  421,
                'message'   =>  'Usuario sin permisos para denegar la promoción',
                'error'     =>  'Usuario sin permisos para denegar la promoción',
            ), 421);
        }
        return response()->json($promocion_pyc);

    }
}
