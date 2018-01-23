<?php

namespace Eccube\Tests\Web\Admin\Order;

use Eccube\Entity\BaseInfo;
use Eccube\Entity\Customer;
use Eccube\Entity\MailHistory;
use Eccube\Entity\MailTemplate;
use Eccube\Entity\Order;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;

class MailControllerTest extends AbstractAdminWebTestCase
{
    /**
     * @var Customer
     */
    protected $Customer;

    /**
     * @var Order
     */
    protected $Order;

    public function setUp()
    {
        parent::setUp();
        $faker = $this->getFaker();
        $this->Member = $this->createMember();
        $this->Customer = $this->createCustomer();
        $this->Order = $this->createOrder($this->Customer);

        $MailTemplate = new MailTemplate();
        $MailTemplate
            ->setName($faker->word)
            ->setMailHeader($faker->word)
            ->setMailFooter($faker->word)
            ->setMailSubject($faker->word)
            ->setCreator($this->Member);
        $this->entityManager->persist($MailTemplate);
        $this->entityManager->flush();
        for ($i = 0; $i < 3; $i++) {
            $this->MailHistories[$i] = new MailHistory();
            $this->MailHistories[$i]
                ->setOrder($this->Order)
                ->setSendDate(new \DateTime())
                ->setMailBody($faker->realText())
                ->setCreator($this->Member)
                ->setMailSubject('mail_subject-'.$i);

            $this->entityManager->persist($this->MailHistories[$i]);
            $this->entityManager->flush();
        }
    }

    public function createFormData()
    {
        $faker = $this->getFaker();
        $form = array(
            'template' => 1,
            'mail_subject' => $faker->word,
            'mail_header' => $faker->paragraph,
            'mail_footer' => $faker->paragraph,
            '_token' => 'dummy',
        );

        return $form;
    }

    public function testIndex()
    {
        $this->client->request(
            'GET',
            $this->generateUrl('admin_order_mail', array('id' => $this->Order->getId()))
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testIndexWithConfirm()
    {
        $form = $this->createFormData();
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_order_mail', array('id' => $this->Order->getId())),
            array(
                'mail' => $form,
                'mode' => 'confirm',
            )
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->expected = $form['mail_footer'];
        $this->actual = $crawler->filter('#mail_mail_footer')->text();
        $this->verify();
    }

    public function testIndexWithComplete()
    {
        $this->client->enableProfiler();
        $form = $this->createFormData();
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_order_mail', array('id' => $this->Order->getId())),
            array(
                'mail' => $form,
                'mode' => 'complete',
            )
        );
        $this->assertTrue($this->client->getResponse()->isRedirect($this->generateUrl('admin_order_mail_complete')));

        $mailCollector = $this->getMailCollector(false);
        $this->assertEquals(1, $mailCollector->getMessageCount());

        $collectedMessages = $mailCollector->getMessages();
        /** @var \Swift_Message $Message */
        $Message = $collectedMessages[0];

        $BaseInfo = $this->container->get(BaseInfo::class);
        $this->expected = '['.$BaseInfo->getShopName().'] '.$form['mail_subject'];
        $this->actual = $Message->getSubject();
        $this->verify();
    }

    public function testView()
    {
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_order_mail_view'),
            array(
                'id' => $this->MailHistories[0]->getId(),
            ),
            array(),
            array(
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'CONTENT_TYPE' => 'application/json',
            )
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testMailAll()
    {
        $form = $this->createFormData();
        $crawler = $this->client->request(
            'GET',
            $this->generateUrl('admin_order_mail_all')
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testMailAllWithConfirm()
    {
        $ids = array();
        for ($i = 0; $i < 5; $i++) {
            $Order = $this->createOrder($this->Customer);
            $ids[] = $Order->getId();
        }

        $form = $this->createFormData();
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_order_mail_all'),
            array(
                'mail' => $form,
                'mode' => 'confirm',
                'ids' => implode(',', $ids),
            )
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->expected = $form['mail_footer'];
        $this->actual = $crawler->filter('#mail_mail_footer')->text();
        $this->verify();
    }

    public function testMailAllWithComplete()
    {
        $this->client->enableProfiler();

        $ids = array();
        for ($i = 0; $i < 5; $i++) {
            $Order = $this->createOrder($this->Customer);
            $ids[] = $Order->getId();
        }

        $form = $this->createFormData();
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_order_mail_all'),
            array(
                'mail' => $form,
                'mode' => 'complete',
                'ids' => implode(',', $ids),
            )
        );
        $this->assertTrue($this->client->getResponse()->isRedirect($this->generateUrl('admin_order_mail_complete')));

        $mailCollector = $this->getMailCollector(false);
        $this->assertEquals(1, $mailCollector->getMessageCount());

        $Messages = $mailCollector->getMessages();

        $this->expected = 10;
        $this->actual = count($Messages);
        $this->verify();

        $Message = $Messages[0];

        $BaseInfo = $this->container->get(BaseInfo::class);
        $this->expected = '['.$BaseInfo->getShopName().'] '.$form['mail_subject'];
        $this->actual = $Message->getSubject();
        $this->verify();
    }

    public function testComplete()
    {
        $this->client->request(
            'GET',
            $this->generateUrl('admin_order_mail_complete')
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }
}
