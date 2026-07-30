<?php
/*
 * LaraUpdater configuration for Forever-love
 * @see https://github.com/pietrocinaglia/laraupdater
 */

return [

    /*
    * Temporary folder to store update before to install it.
    */
    'tmp_folder_name' => 'tmp',

    /*
    * Script's filename called during the update.
    */
    'script_filename' => 'upgrade.php',

    /*
    * Remote fallback only.
    *
    * Self-hosted archives belong in storage/app/updates/ (laraupdater.json + RELEASE-*.zip);
    * the updater reads those off disk and never touches this URL. It used to serve them from
    * public/updates/ over HTTP, which made the full application source downloadable by
    * anyone who guessed the filename.
    *
    * Set LARA_UPDATER_URL only when updates come from a genuinely different server. Use
    * https: the response is extracted over the running application, so anything able to
    * tamper with it can replace your code.
    */
    'update_baseurl' => env('LARA_UPDATER_URL') ?: rtrim(config('app.url'), '/') . '/updates',

    /*
    * Set a middleware for the route: updater.update
    * Restrict to admin/super-admin roles only.
    */
    'middleware' => ['web', 'auth', 'role:admin|super-admin'],

    /*
    * Set which users can perform an update;
    * false = allow any user passing middleware (all admin|super-admin)
    * [1, 2, 3] = restrict to specific user IDs
    */
    'allow_users_id' => false,
];
