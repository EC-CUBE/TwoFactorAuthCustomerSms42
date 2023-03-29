<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\TwoFactorAuthCustomerSms42\Controller;

use Eccube\Entity\Customer;
use Plugin\TwoFactorAuthCustomer42\Controller\TwoFactorAuthCustomerController;
use Plugin\TwoFactorAuthCustomer42\Form\Type\TwoFactorAuthPhoneNumberTypeCustomer;
use Plugin\TwoFactorAuthCustomer42\Form\Type\TwoFactorAuthSmsTypeCustomer;
use Plugin\TwoFactorAuthCustomer42\Service\CustomerTwoFactorAuthService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Api\V2010\Account\MessageInstance;

class TwoFactorAuthCustomerSmsController extends TwoFactorAuthCustomerController
{
    /**
     * SMS認証 送信先入力画面.
     *
     * @Route("/mypage/two_factor_auth/tfa/sms/send_onetime", name="plg_customer_2fa_sms_send_onetime", methods={"GET", "POST"})
     * @Template("TwoFactorAuthCustomerSms42/Resource/template/default/tfa/sms/send.twig")
     */
    public function inputPhoneNumber(Request $request)
    {
        if ($this->isTwoFactorAuthed()) {
            return $this->redirectToRoute($this->getCallbackRoute());
        }

        $error = null;
        /** @var Customer $Customer */
        $Customer = $this->getUser();
        $builder = $this->formFactory->createBuilder(TwoFactorAuthPhoneNumberTypeCustomer::class);
        // 入力フォーム生成
        $form = $builder->getForm();

        // デバイス認証済み電話番号が設定済みの場合は優先して利用
        $phoneNumber = ($Customer->getDeviceAuthedPhoneNumber() !== null)
            ? $Customer->getDeviceAuthedPhoneNumber()
            : $Customer->getTwoFactorAuthedPhoneNumber();
        if (!empty($phoneNumber)) {
            $form->remove('phone_number');
        }
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                if ($Customer->getTwoFactorAuthType() === null || !$phoneNumber) {
                    // 初回認証時
                    $phoneNumber = $form->get('phone_number')->getData();
                }
                // 入力された電話番号へワンタイムコードを送信
                $this->sendToken($Customer, $phoneNumber);

                $response = $this->redirectToRoute('plg_customer_2fa_sms_input_onetime');

                // 送信電話番号をセッションへ一時格納
                $this->session->set(
                    CustomerTwoFactorAuthService::SESSION_AUTHED_PHONE_NUMBER,
                    $phoneNumber
                );

                return $response;
            } else {
                $error = trans('front.2fa.sms.send.failure_message');
            }
        }

        return [
            'form' => $form->createView(),
            'Customer' => $Customer,
            'phoneNumber' => $phoneNumber,
            'error' => $error,
        ];
    }

    /**
     * SMS認証 ワンタイムトークン入力画面.
     *
     * @Route("/mypage/two_factor_auth/tfa/sms/input_onetime", name="plg_customer_2fa_sms_input_onetime", methods={"GET", "POST"})
     * @Template("TwoFactorAuthCustomerSms42/Resource/template/default/tfa/sms/input.twig")
     */
    public function inputToken(Request $request)
    {
        if ($this->isTwoFactorAuthed()) {
            return $this->redirectToRoute($this->getCallbackRoute());
        }

        $error = null;
        /** @var Customer $Customer */
        $Customer = $this->getUser();
        $builder = $this->formFactory->createBuilder(TwoFactorAuthSmsTypeCustomer::class);
        // 入力フォーム生成
        $form = $builder->getForm();
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $token = $form->get('one_time_token')->getData();
                if (!$this->checkToken($Customer, $token)) {
                    // ワンタイムトークン不一致 or 有効期限切れ
                    $error = trans('front.2fa.onetime.invalid_message__reinput');
                } else {
                    // 送信電話番号をセッションより取得
                    $phoneNumber = $this->session->get(CustomerTwoFactorAuthService::SESSION_AUTHED_PHONE_NUMBER);
                    // ワンタイムトークン一致
                    // 二段階認証完了
                    $Customer->setTwoFactorAuthedPhoneNumber($phoneNumber);
                    $this->entityManager->persist($Customer);
                    $this->entityManager->flush();

                    $response = $this->redirectToRoute($this->getCallbackRoute());
                    $response->headers->setCookie(
                        $this->customerTwoFactorAuthService->createAuthedCookie(
                            $Customer,
                            $this->getCallbackRoute()
                        )
                    );

                    return $response;
                }
            } else {
                $error = trans('front.2fa.onetime.invalid_message__reinput');
            }
        }

        return [
            'form' => $form->createView(),
            'Customer' => $Customer,
            'error' => $error,
        ];
    }

    /**
     * ワンタイムトークンチェック.
     *
     * @param Customer $Customer
     * @param $token
     *
     * @return boolean
     */
    private function checkToken(Customer $Customer, $token): bool
    {
        $now = new \DateTime();

        // フォームからのハッシュしたワンタイムパスワードとDBに保存しているワンタイムパスワードのハッシュは一致しているかどうか
        if ($Customer->getTwoFactorAuthOneTimeToken() !== $this->customerTwoFactorAuthService->hashOneTimeToken($token)
            || $Customer->getTwoFactorAuthOneTimeTokenExpire() < $now) {
            return false;
        }

        return true;
    }

    /**
     * ワンタイムトークンを送信.
     *
     * @param Customer $Customer
     * @param string $phoneNumber
     *
     * @return MessageInstance
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws ConfigurationException
     * @throws TwilioException
     * @throws \Exception
     */
    private function sendToken($Customer, $phoneNumber)
    {
        // ワンタイムトークン生成・保存
        $token = $this->customerTwoFactorAuthService->generateOneTimeTokenValue();

        $Customer->setTwoFactorAuthOneTimeToken($this->customerTwoFactorAuthService->hashOneTimeToken($token));
        $Customer->setTwoFactorAuthOneTimeTokenExpire($this->customerTwoFactorAuthService->generateExpiryDate());
        $this->entityManager->persist($Customer);
        $this->entityManager->flush();

        // ワンタイムトークン送信メッセージをレンダリング
        $twig = 'TwoFactorAuthCustomer42/Resource/template/default/sms/onetime_message.twig';
        $body = $this->twig->render($twig, [
            'Customer' => $Customer,
            'token' => $token,
        ]);

        // SMS送信
        return $this->customerTwoFactorAuthService->sendBySms($phoneNumber, $body);
    }
}
