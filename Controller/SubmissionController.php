<?php
/**
 * Submission controller.
 *
 * @category None
 * @package  TenelevenFormHandlerBundle
 * @author   Justin Hilles <justin@1011i.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version  0.1
 * @link     none
 *
 */

namespace Teneleven\Bundle\FormHandlerBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Teneleven\Bundle\FormHandlerBundle\Entity\Submission;
use Teneleven\Bundle\FormHandlerBundle\Form\SubmissionType;

/**
 * Submission controller.
 *
 * @category None
 * @package  TenelevenFormHandlerBundle
 * @author   Justin Hilles <justin@1011i.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version  0.1
 * @link     none
 *
 */
class SubmissionController extends Controller
{
    protected $attachments = array();

    /**
     * Lists all Submission entities.
     *
     * @return Response
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $entities = $em->getRepository('TenelevenFormHandlerBundle:Submission')->findAll();

        return $this->render(
            'TenelevenFormHandlerBundle:Submission:index.html.twig', array(
                'entities' => $entities,
            )
        );
    }

    /**
     * Finds and displays a Submission entity.
     *
     * @param int $id ID Submission
     * 
     * @return Response
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('TenelevenFormHandlerBundle:Submission')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Submission entity.');
        }

        $entity->setIsViewed(true);

        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);
        $em->flush();

        $data = unserialize($entity->getData());

        $form = $this->createForm($entity->getType(), $data);

        return $this->render(
            'TenelevenFormHandlerBundle:Submission:show.html.twig', array(
                'form'      => $form->createView(),
                'entity'    => $entity
            )
        );
    }

    /**
     * Handle all Form Submissions
     * 
     * @param string  $type    Form Type
     * @param Request $request Request
     * 
     * @return null
     */
    public function handleAction($type, Request $request)
    {
        $form = $this->createForm($type);

        $form->handleRequest($request);

        if ($form->isValid()) {

            $values = $form->getData();

            $this->handleAttachments($values);

            $submission = new Submission();
            $submission->setType($type);
            $submission->setData(serialize($values));

            $em = $this->getDoctrine()->getManager();
            $em->persist($submission);
            $em->flush();

            $this->sendNotificationEmail($type, $form, $submission);

            return $this->render(
                $this->container->getParameter(sprintf('teneleven_form_handler.%s.thanks_template', $type)),
                array(
                    'submission' => $submission, 
                    'form' => $form->createView()
                )
            );         
        }
    }

    /**
     * Find attachments in data array
     * 
     * @param  array &$data [description]
     * @return [type]       [description]
     */
    public function handleAttachments(&$data)
    {
        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $this->attachments[] = $value;

                //Unset field because we cant serialize Objects
                unset($data[$key]);
            }
        }
    }

    /**
     * Create the Form
     * 
     * @param string $type     Type name of Form
     * @param string $template String of template filename
     * 
     * @return Response
     */
    public function formAction($type, $template = 'TenelevenFormHandlerBundle:Submission:_form.html.twig')
    {
        $form = $this->createForm(
            $type,
            array(), 
            array('action' => $this->generateUrl('teneleven_formhandler_handle', array('type' => $type)))
        );

        return $this->render(
            $template, 
            array(
                'form' => $form->createView(),
                'type' => $type
            )
        );
    }

    /**
     * Sends Email following parameters in config
     * 
     * @param string $type Service key of Form
     * @param Object $form Form
     * 
     * @return null
     */
    protected function sendNotificationEmail($type, $form, $submission)
    {
        try {

            $message = \Swift_Message::newInstance()
                ->setSubject($this->container->getParameter(sprintf('teneleven_form_handler.%s.subject', $type)))
                ->setFrom($this->container->getParameter(sprintf('teneleven_form_handler.%s.from', $type)))
                ->setTo($this->container->getParameter(sprintf('teneleven_form_handler.%s.to', $type)))
                ->setContentType($this->container->getParameter(sprintf('teneleven_form_handler.%s.content_type', $type)))
                ->setBody(
                    $this->renderView(
                        $this->container->getParameter(sprintf('teneleven_form_handler.%s.email_template', $type)),
                        array('form' => $form->createView(), 'submission' => $submission)
                    )
                )
            ;

            foreach ($this->attachments as $file) {
                $attachment = \Swift_Attachment::fromPath($file->getRealPath())->setFilename($file->getClientOriginalName());
                $message->attach($attachment);                    
            }

            $result = $this->get('mailer')->send($message);

        } catch(Exception $e) {
            //@todo Need to do something here
        }
    }
}
