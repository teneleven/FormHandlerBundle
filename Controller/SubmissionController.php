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

            $submission = new Submission();
            $submission->setType($type);
            $submission->setData(serialize($values));

            $em = $this->getDoctrine()->getManager();
            $em->persist($submission);
            $em->flush();

            $this->sendNotificationEmail($type, $values);

            return $this->render(
                'TenelevenFormHandlerBundle:Submission:thanks.html.twig',
                array('submission' => $submission)
            );         
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
     * @param string $type   Service key of Form
     * @param array  $values Array of form values
     * 
     * @return null
     */
    protected function sendNotificationEmail($type, array $values)
    {
        try {
            $message = \Swift_Message::newInstance()
                ->setSubject($this->container->getParameter(sprintf('teneleven_form_handler.%s.subject', $type)))
                ->setFrom($this->container->getParameter(sprintf('teneleven_form_handler.%s.from', $type)))
                ->setTo($this->container->getParameter(sprintf('teneleven_form_handler.%s.to', $type)))
                ->setContentType($this->container->getParameter(sprintf('teneleven_form_handler.%s.content_type', $type)))
                ->setBody(
                    $this->renderView(
                        $this->container->getParameter(sprintf('teneleven_form_handler.%s.template', $type)),
                        array('values' => $values)
                    )
                );

            $result = $this->get('mailer')->send($message);   
        } catch(Exception $e) {
            //@todo Need to do something here
        }
    }
}
