<?php
/*
*
* Auto Accept Orders
* Created By Idan Ben-Ezra
*
* Copyrights @ Jetserver Web Hosting
* www.jetserver.net
*
* Hook version 1.0.1
*
**/
if (!defined("WHMCS")) die("This file cannot be accessed directly");

//add_hook('PendingOrder', 1, function ($vars) {
//    try {
//        $settings = AutoAcceptOrders_settings();
//        // Check if invoice exists
//        if ($vars['invoiceId']) {
//            $results = localAPI('GetInvoice', ['invoiceid' => $vars['invoiceId']]);
//            if ($results['result'] != 'success') {
//                throw new Exception("Unable to get Invoice #{$vars['invoiceId']}, {$results['message']}");
//            } else {
//                logActivity("Order #{$vars['orderId']} is being accepted");
//                $results = localAPI('AcceptOrder', [
//                    'orderid' => $vars['orderId'],
//                    'autosetup' => $settings['autosetup'],
//                    'sendemail' => $settings['sendemail'],
//                    'sendregistrar' => $settings['sendregistrar'],
//                    'registrar' => $settings['registrar']
//                ]);
//                // If success, log activity.
//                if ($results['result'] != 'success') {
//                    throw new Exception("Unable to accept Order #{$vars['orderId']},{$results['message']}");
//                } else {
//                    logActivity("Order #{$vars['orderId']} accepted");
//                }
//
//            }
//        }
//    } catch (Exception $e) {
//        logActivity("[Auto Accept Orders] {$e->getMessage()}");
//        //return error
//        return [
//            'error' => $e->getMessage()
//        ];
//    }
//    return [
//        'success' => true
//    ];
//
//});

add_hook('InvoiceCreation', 1, function($vars) {
    //log creationg of invoice
    logActivity("Invoice #{$vars['invoiceid']} created");
});
//add_hook to accept order with invoice paid
//add_hook('InvoicePaid', 1, function($vars) {
//    try {
//        $settings = AutoAcceptOrders_settings();
//        // Check if invoice exists
//        if ($vars['invoiceid']) {
//            $results = localAPI('GetInvoice', ['invoiceid' => $vars['invoiceid']]);
//            if ($results['result'] != 'success') {
//                throw new Exception("Unable to get Invoice #{$vars['invoiceid']}, {$results['message']}");
//            } else {
//                // Check if invoice is paid
//                if ($results['status'] == 'Paid') {
//                    // Check if invoice has an order
//                    if ($results['orderid']) {
//                        logActivity("Order #{$results['orderid']} is being accepted");
//                        $results = localAPI('AcceptOrder', [
//                            'orderid' => $results['orderid'],
//                            'autosetup' => $settings['autosetup'],
//                            'sendemail' => $settings['sendemail'],
//                            'sendregistrar' => $settings['sendregistrar'],
//                            'registrar' => $settings['registrar']
//                        ]);
//                        // If success, log activity.
//                        if ($results['result'] != 'success') {
//                            throw new Exception("Unable to accept Order #{$results['orderid']},{$results['message']}");
//                        } else {
//                            logActivity("Order #{$results['orderid']} accepted custom hook");
//                        }
//                    }
//                }
//            }
//        }
//    } catch (Exception $e) {
//        logActivity("[Auto Accept Orders] {$e->getMessage()}");
//        //return error
//        return [
//            'error' => $e->getMessage()
//        ];
//    }
//    return [
//        'success' => true
//    ];
//});

/*******************************
 * Auto Accept Orders Settings *
 ******************************/
function AutoAcceptOrders_settings(): array
{
    return [
        //'apiuser' => 'XXXXXX', // one of the admins username
        'autosetup' => true, // determines whether product provisioning is performed
        'registrar' =>'registrarmodule', // determines which registrar will get the registration request [optional]
        'sendregistrar' => true, // determines whether domain automation is performed
        'sendemail' => false, // sets if welcome emails for products and registration confirmation emails for domains should be sent
        'ispaid' => true, // set to true if you want to accept only paid orders
    ];
}