(function ($) {
    'use strict';
    $(document).ready(function () {


        /**
         * Display custom message into a particular HTML element.
         *
         * @param {string} message_to_display Message
         */
        function wms_display_message( message_to_display ){
            $('#wms_rslt').empty().addClass('notice').append(message_to_display);
        }

        function wms_init_result_tabs() {
          $('div.tab').hide();
          $(document).on("click", ".wms-head", function (e)
          {
            e.preventDefault();
            $('ul#tabs-nav li[class^="wms-table"]').removeClass('wms-tab-color');
            $(this).closest('li').addClass('wms-tab-color');
            var tableid = $(this).attr('id');
            var div_elem =  $('div.tab.'+tableid);
            var table_elem = $( "table#" + tableid );
            $('div.tab').hide();
            $(div_elem).show();
            $(table_elem).show();
                if ( $.fn.dataTable.isDataTable( table_elem ) ) {
                    $(table_elem).DataTable().destroy(); 
                }
                $(table_elem).DataTable({
                    autoWidth: false,
                    columnDefs: [
                        {
                            targets: ['_all'],
                            className: 'mdc-data-table__cell'
                        }
                    ]
                } );
            });
        }

        /**
         * Send dump data to KPAX.
         *
         * @param {string} remote_site_url
         * @param {string} remote_db_table_prefix
         * @param {string} remote_db_url
         * @param {string} dump_folder_name
         * @param {string} site_id
         */
        function wms_send_dump_data_to_kpax( remote_site_url, remote_db_table_prefix, remote_db_url, dump_folder_name,site_id){
            $.post(
                ajaxurl, {
                    action: 'send_dump_to_kpax',
                    data : {
                        remote_site_url : remote_site_url,
                        remote_db_table_prefix : remote_db_table_prefix,
                        remote_db_url : remote_db_url,
                        dump_folder_name : dump_folder_name,
                        site_id     : site_id
                    },
                    security: wms_object.wms_ajax_security
                },
                function( data ) {
                  if ( '102' == data ) {
                    $.post(this);
                    return;
                  } else {
                    // wms_display_message(data);
                    $.post(
                        ajaxurl,{
                            action : 'get-decode-results',
                            security: wms_object.wms_ajax_security
                        }, function( data ){
                            $('#wms_comparison_rslt').empty().show().html(data);
                            try{
                                $('.wpdms-post-comparison-results').DataTable({ searching: false, paging: true, info: false });
                            } catch(error){
                                console.info(error);
                            }
                            wms_init_result_tabs();
                            $('li.wms-nav-block:nth(0) a').trigger('click')
                        }
                    );
                    $.unblockUI();
                    return data;
                  }
                }
            );
        }

        $(document).on('click', '#wms-test-key', function (e) {
            e.preventDefault();
            var site_url = $('#site_url').val();
            var connection_key = $('#connection_key').val();
            $(this).html(wms_object.loading_message)
            if ($('#debug').length) {
                $('#debug').html('');
            }
            $.post(
                ajaxurl,
                {
                    action: "test_connection",
                    site_url: site_url,
                    connection_key: connection_key,
                },
                function (data) {
                    if ($('#debug').length) {
                        $('#debug').html(data);
                    }
                    else {
                        $('#wms-test-key').after('<p id="debug"  style="margin-top: 10px;" >' + data + '</p>');
                    }
                    $('#wms-test-key').html(wms_object.test_connection_message)
                }
            );

        });

        $(document).on('click', '#generate-key', function (e) {
            e.preventDefault();
            $.post(
                ajaxurl,
                {
                    action: "generate-wms-key"
                },
                function (key) {
                    $("input[name='wms-site-key']").val(key);
                }
            );
        });
        $(document).on('click', '#wms_sync_btn', function (e) {
            e.preventDefault();
            var remote, decoded_remote_data, remote_folder_name, remote_file_name, remote_ajax_url, remote_site_url ,
            remote_db_table_prefix,remote_db_url, site_id;
            site_id = $("#wms_sync_select").val();
            window.wms_local_dump_completed = false;
            window.wms_remote_dump_completed = false;
            $.blockUI({'message' : wms_object.dump_start_message , 'css' : { padding : '10px' } });
            $.post(
                ajaxurl,
                {
                    action: 'start_wms_sync',
                    site_id: site_id,
                    security : wms_object.wms_ajax_security
                },
                function (data) {
                    try{
                        var decoded_data = JSON.parse(data);
                        // get data related to the local website when we are dumping with php in long mode.
                        try {
                            var local        = JSON.parse(decoded_data.local);
                            var folder_name  = local.dump_folder_name;
                            var file_name    = local.dump_file_name;
                        } catch(e) {
                            // the dump is in fast mode so we can set the var to true.
                            window.wms_local_dump_completed = true;
                        }

                        // get data related to the remote website
                        try{
                            remote = decoded_data.remote.remote_dump_informations;
                            remote_folder_name  = decoded_data.remote.dump_folder_name;
                            decoded_remote_data = JSON.parse(remote);
                            remote_file_name    = decoded_remote_data.dump_file_name;
                            remote_ajax_url     = decoded_remote_data.ajax_site_url;

                            remote_site_url        = decoded_data.remote.site_url;
                            remote_db_table_prefix = decoded_data.remote.prefix;
                            remote_db_url          = decoded_data.remote.db_url;

                            if ( 200 === decoded_remote_data && window.wms_local_dump_completed  ){
                                wms_send_dump_data_to_kpax( remote_site_url, remote_db_table_prefix, remote_db_url, remote_folder_name, site_id );
                                return;
                            }

                            if ( 200 === decoded_remote_data ){
                                window.wms_remote_dump_completed = true;
                            }
                        } catch ( e ){
                            window.wms_remote_dump_completed = true;
                        }

                        //check if the dump with php in long mode is completed or not on the remote or local website.
                        var dump_instance = setInterval(function(){
                            if ( ! window.wms_local_dump_completed && ajaxurl ){
                                $.post(
                                    ajaxurl, {
                                        action: 'check_if_dump_is_completed',
                                        data: {
                                            dump_folder_name: folder_name,
                                            site_id: site_id,
                                            file_name: file_name
                                        },
                                        security: wms_object.wms_ajax_security
                                    },
                                    function (data) {
                                        if (data === '200') {
                                            window.wms_local_dump_completed = true;
                                        }
                                    }
                                );
                            }

                            if ( ! window.wms_remote_dump_completed && remote_ajax_url ){
                                $.post(
                                    remote_ajax_url, {
                                        action: 'check_if_remote_dump_is_completed',
                                        data: {
                                            dump_folder_name: remote_folder_name,
                                            site_id: site_id,
                                            file_name: remote_file_name
                                        },
                                        security: wms_object.wms_ajax_security
                                    },
                                    function (data) {
                                        if (data === '200') {
                                            window.wms_remote_dump_completed = true;
                                        }
                                    }
                                );
                            }


                            if ( window.wms_local_dump_completed && window.wms_remote_dump_completed ){
                                clearInterval(dump_instance);
                                wms_send_dump_data_to_kpax( remote_site_url, remote_db_table_prefix, remote_db_url, remote_folder_name, site_id );
                            }
                        },5000);

                    } catch(e){
                        wms_display_message(data);
                        $.unblockUI();
                    }
                }
            );
        });


        $(document).ready(function() {

          wms_init_result_tabs();
          $( '#tabs' ).tab();
        } );

    });
})(jQuery);
