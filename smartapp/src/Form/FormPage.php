<?php

namespace Drupal\smartapp\Form;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

require 'vendor/autoload.php';


class FormPage extends FormBase
{

  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $form['firstName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];

    $form['lastName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('subject'),
      '#required' => TRUE,
    ];

    $form['message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('E-mail'),
      '#required' => TRUE,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
    ];

    return $form;
  }

  public function getFormId()
  {
    return 'smart_app';
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $email = $form_state->getValue('email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('Email is incorrect'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $mail = $form_state->getValue('email');
    $subject = $form_state->getValue('subject');
    $firstName = $form_state->getValue('firstName');
    $lastName = $form_state->getValue('lastName');
    $body = $form_state->getValue('message');
    $body = $body['value'];
    $hubspot = $this->create_hubspot($mail, $firstName, $lastName);
    if (strlen($hubspot) > 0) {
      \Drupal::logger('smartapp')->error('error creating contact:  %error.',
        array(
          '%error' => $hubspot,
        ));
    }
    $sent = $this->send_email($mail, $subject, $body);
    if ($sent) {
      drupal_set_message(t('Mail sent'));
      \Drupal::logger('smartapp')->notice('mail sent to:  %to.',
        array(
          '%to' => $mail,
        ));
    } else {
      drupal_set_message(t('An error occurred and processing did not complete.'), 'error');
    }
  }


  function send_email($to, $subject, $body)
  {
    $mail = new PHPMailer(true);
    try {
      $senderMail = 'smartapp22@mail.ru';

      $mail->SMTPDebug = SMTP::DEBUG_SERVER;
      $mail->isSMTP();
      $mail->Host = 'smtp.mail.ru';
      $mail->SMTPAuth = true;
      $mail->Username = $senderMail;
      $mail->Password = 'zaq1!sw2';
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port = 587;

      $mail->setFrom($senderMail, 'Mailer');
      $mail->addAddress($to);

      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body = $body;
      $mail->AltBody = 'your client not support html';

      $mail->send();
      return true;
    } catch (Exception $e) {
      \Drupal::logger('smartapp')->error('error sending email:  %error.',
        array(
          '%error' => $e,
        ));
      return false;
    }
  }

  function create_hubspot($email, $firstName, $lastName)
  {
    $arr = array(
      'properties' => array(
        array(
          'property' => 'email',
          'value' => $email
        ),
        array(
          'property' => 'firstname',
          'value' => $firstName
        ),
        array(
          'property' => 'lastname',
          'value' => $lastName
        )
      )
    );
    $post_json = json_encode($arr);
    $hapikey = '57acb43f-cbd2-4ffa-b87b-40583f2ed7c7';
    $endpoint = 'https://api.hubapi.com/contacts/v1/contact?hapikey=' . $hapikey;
    $ch = @curl_init();
    @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //simpliest way to solve problem for test task not for production
    @curl_setopt($ch, CURLOPT_POST, true);
    @curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);
    @curl_setopt($ch, CURLOPT_URL, $endpoint);
    @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = @curl_exec($ch);
    $status_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_errors = curl_error($ch);
    @curl_close($ch);
    return $curl_errors;
  }
}
