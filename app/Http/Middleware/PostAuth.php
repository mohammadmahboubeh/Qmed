<?php

namespace App\Http\Middleware;

use App;
use Closure;
use Config;
use DB;
use File;
use Response;
use Session;
use URL;

class PostAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $install = DB::table('practiceinfo')->first();
        // Check if e-mail service is setup
        if (env('MAIL_HOST') == 'mailtrap.io') {
            return redirect()->route('setup_mail');
        }
        // Check if Google refresh token registered if Google is used as e-mail service
        if (env('MAIL_HOST') == 'smtp.gmail.com') {
            if ($install->google_refresh_token == '') {
                if (route('dashboard') != 'http://localhost/nosh') {
                    return redirect()->route('googleoauth');
                }
            }
        }
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        // Migrate old NOSH standard template to current
        if ($practice->encounter_template == 'standardmedical' || $practice->encounter_template == 'standardmedical1') {
            $update['encounter_template'] = 'medical';
            DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->update($update);
        }
        $messages = DB::table('messaging')->where('mailbox', '=', Session::get('user_id'))->where('read', '=', null)->count();
        Session::put('messages_count', $messages);
        Session::put('notification_run', 'true');
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $locale = Config::get('app.locale');
        if ($practice->locale == null) {
            $practice_data['locale'] = $locale;
            DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->update($practice_data);
            $practice_locale = $locale;
        } else {
            $practice_locale = $practice->locale;
        }
        if ($user->locale == null) {
            $user_data['locale'] = $practice_locale;
            DB::table('users')->where('id', '=', Session::get('user_id'))->update($user_data);
        } else {
            $locale = $user->locale;
        }
        App::setLocale($locale);
        Session::put('user_locale', $locale);
        Session::put('practice_locale', $practice_locale);
        // Check if pNOSH for provider that patient's demographic supplementary tables exist
        if (Session::get('patient_centric') == 'yp') {
            $relate = DB::table('demographics_notes')->where('pid', '=', Session::get('pid'))->where('practice_id', '=', Session::get('practice_id'))->first();
            if (!$relate) {
                $data1 = [
    				'billing_notes' => '',
    				'imm_notes' => '',
    				'pid' => Session::get('pid'),
    				'practice_id' => Session::get('practice_id')
    			];
    			DB::table('demographics_notes')->insert($data1);
            }
        }
        return $next($request);
    }
}
