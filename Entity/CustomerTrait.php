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

namespace Plugin\TwoFactorAuthCustomerSms42\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Customer")
 */
trait CustomerTrait
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="two_factor_authed_phone_number", type="string", length=14, nullable=true)
     */
    private ?string $two_factor_authed_phone_number = null;

    /**
     * @var ?string
     *
     * @ORM\Column(name="two_factor_auth_one_time_token", type="string", length=255, nullable=true)
     */
    private ?string $two_factor_auth_one_time_token = null;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="two_factor_auth_one_time_token_expire", type="datetimetz", nullable=true)
     */
    private $two_factor_auth_one_time_token_expire;

    /**
     * @return string
     */
    public function getTwoFactorAuthedPhoneNumber(): ?string
    {
        return $this->two_factor_authed_phone_number;
    }

    /**
     * @param string|null $two_factor_authed_phone_number
     */
    public function setTwoFactorAuthedPhoneNumber(?string $two_factor_authed_phone_number): void
    {
        $this->two_factor_authed_phone_number = $two_factor_authed_phone_number;
    }

    /**
     * @param string $hashedOneTimePassword
     *
     * @return void
     */
    public function createTwoFactorAuthOneTimeToken(string $hashedOneTimePassword): void
    {
        $now = new \DateTime();

        // ワンタイムパスワードをハッシュする
        $this->setTwoFactorAuthOneTimeToken($hashedOneTimePassword);
        $this->setTwoFactorAuthOneTimeTokenExpire($now->modify('+5 mins'));
    }

    /**
     * @return string
     */
    public function getTwoFactorAuthOneTimeToken(): ?string
    {
        return $this->two_factor_auth_one_time_token;
    }

    /**
     * @param string|null $two_factor_auth_one_time_token
     */
    public function setTwoFactorAuthOneTimeToken(?string $two_factor_auth_one_time_token): void
    {
        $this->two_factor_auth_one_time_token = $two_factor_auth_one_time_token;
    }

    /**
     * Get resetExpire.
     *
     * @return \DateTime|null
     */
    public function getTwoFactorAuthOneTimeTokenExpire()
    {
        return $this->two_factor_auth_one_time_token_expire;
    }

    /**
     * Set oneTimeTokenExpire.
     *
     * @param null $deviceAuthOneTimeTokenExpire
     * @return Customer
     */
    public function setTwoFactorAuthOneTimeTokenExpire($deviceAuthOneTimeTokenExpire = null)
    {
        $this->two_factor_auth_one_time_token_expire = $deviceAuthOneTimeTokenExpire;

        return $this;
    }
}
