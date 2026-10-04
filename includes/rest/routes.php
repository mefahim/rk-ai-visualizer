<?php
namespace RK\AIVisualizer\Rest;
use RK\AIVisualizer\Core\Engine;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Routes {
    public static function register() {
        $namespaces=array('rk-ai/v1','rk/v1');
        foreach ($namespaces as $ns) {
            register_rest_route($ns,'/visualizer/quota',array('methods'=>'GET','callback'=>array(__CLASS__,'quota'),'permission_callback'=>'__return_true'));
            register_rest_route($ns,'/visualizer/generate',array('methods'=>'POST','callback'=>array(__CLASS__,'generate'),'permission_callback'=>'__return_true'));
            register_rest_route($ns,'/visualizer/status',array('methods'=>'GET','callback'=>array(__CLASS__,'status'),'permission_callback'=>'__return_true'));
            register_rest_route($ns,'/visualizer/lead',array('methods'=>'POST','callback'=>array(__CLASS__,'lead'),'permission_callback'=>'__return_true'));
            register_rest_route($ns,'/visualizer/(?P<visualizer>[a-z0-9_-]+)/quota',array('methods'=>'GET','callback'=>array(__CLASS__,'quota'),'permission_callback'=>'__return_true'));
            register_rest_route($ns,'/visualizer/(?P<visualizer>[a-z0-9_-]+)/generate',array('methods'=>'POST','callback'=>array(__CLASS__,'generate'),'permission_callback'=>'__return_true'));
            register_rest_route($ns,'/visualizer/(?P<visualizer>[a-z0-9_-]+)/status',array('methods'=>'GET','callback'=>array(__CLASS__,'status'),'permission_callback'=>'__return_true'));
            register_rest_route($ns,'/visualizer/(?P<visualizer>[a-z0-9_-]+)/lead',array('methods'=>'POST','callback'=>array(__CLASS__,'lead'),'permission_callback'=>'__return_true'));
        }
    }
    private static function engine() { return new Engine(); }
    private static function slug( $request ) { $slug=$request->get_param('visualizer'); if ( null===$slug || ''===$slug ) return 'flooring'; return is_string($slug)?sanitize_key($slug):''; }
    public static function quota( $request ) { return self::engine()->quota(self::slug($request)); }
    public static function generate( $request ) { return self::engine()->generate(self::slug($request),$request); }
    public static function status( $request ) { return self::engine()->status($request,self::slug($request)); }
    public static function lead( $request ) { return self::engine()->lead(self::slug($request),$request); }
}
