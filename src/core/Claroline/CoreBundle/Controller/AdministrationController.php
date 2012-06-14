<?php

namespace Claroline\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Group;
use Claroline\CoreBundle\Form\ProfileType;
use Claroline\CoreBundle\Form\GroupType;
use Claroline\CoreBundle\Form\GroupSettingsType;
use Claroline\CoreBundle\Form\ClarolineSettingsType;
use Claroline\CoreBundle\Library\Workspace\Configuration;

/**
 * Controller of the platform administration section (users, groups,
 * workspaces, platform settings, etc.).
 */
class AdministrationController extends Controller
{
    /**
     * Displays the administration section index.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        return $this->render('ClarolineCoreBundle:Administration:index.html.twig');
    }

    /**
     * Displays the user creation form.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function userCreationFormAction()
    {
        $userRoles = $this->get('security.context')->getToken()->getUser()->getOwnedRoles();
        $form = $this->createForm(new ProfileType($userRoles));

        return $this->render(
            'ClarolineCoreBundle:Administration:user_creation_form.html.twig',
            array('form_complete_user' => $form->createView())
        );
    }

    /**
     * Creates an user (and its personal workspace) and redirects to the user list.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function createUserAction()
    {
        $request = $this->get('request');
        $userRoles = $this->get('security.context')->getToken()->getUser()->getOwnedRoles();
        $form = $this->get('form.factory')->create(new ProfileType($userRoles), new User());
        $form->bindRequest($request);

        if ($form->isValid()) {
            $user = $form->getData();
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($user);
            $type = Configuration::TYPE_SIMPLE;
            $config = new Configuration();
            $config->setWorkspaceType($type);
            $config->setWorkspaceName('my workspace');
            $wsCreator = $this->get('claroline.workspace.creator');
            $workspace = $wsCreator->createWorkspace($config, $user);
            $workspace->setType('user_repository');
            $user->addRole($workspace->getManagerRole());
            $user->setPersonnalWorkspace($workspace);
            $em->persist($workspace);
            $em->flush();

            return $this->redirect($this->generateUrl('claro_admin_user_list'));
        }

        return $this->render(
            'ClarolineCoreBundle:Administration:user_creation_form.html.twig',
            array('form_complete_user' => $form->createView())
        );
    }

    /**
     * Deletes an user from the platform.
     *
     * @param integer $userId
     *
     * @throws Exception if the user to be deleted is the current logged user
     *
     * @return type
     */
    public function deleteUserAction($userId)
    {
        if ($userId !== $this->get('security.context')->getToken()->getUser()->getId()) {
            $em = $this->getDoctrine()->getEntityManager();
            $user = $em->getRepository('ClarolineCoreBundle:User')->find($userId);
            $em->remove($user);
            $em->flush();

            return $this->redirect($this->generateUrl('claro_admin_user_list'));
        }

        throw new \Exception('An user cannot delete his own profile.');
    }

    /**
     * Displays the platform user list.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function userListAction()
    {
        $em = $this->getDoctrine()->getEntityManager();
        $users = $em->getRepository('ClarolineCoreBundle:User')->findAll();

        return $this->render(
            'ClarolineCoreBundle:Administration:user_list.html.twig',
            array('users' => $users)
        );
    }

    /**
     * Displays the group creation form.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function groupCreationFormAction()
    {
        $form = $this->createForm(new GroupType(), new Group());

        return $this->render(
            'ClarolineCoreBundle:Administration:group_creation_form.html.twig',
            array('form_group' => $form->createView())
        );
    }

    /**
     * Creates a group and redirects to the group list.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function createGroupAction()
    {
        $request = $this->get('request');
        $form = $this->get('form.factory')->create(new GroupType(), new Group());
        $form->bindRequest($request);

        if ($form->isValid()) {
            $group = $form->getData();
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($group);
            $em->flush();

            return $this->redirect($this->generateUrl('claro_admin_group_list'));
        }

        return $this->render(
            'ClarolineCoreBundle:Administration:group_creation_form.html.twig',
            array('form_group' => $form->createView())
        );
    }

    /**
     * Displays the platform group list.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function groupListAction()
    {
        $em = $this->getDoctrine()->getEntityManager();
        $groups = $em->getRepository('ClarolineCoreBundle:Group')->findAll();

        return $this->render(
            'ClarolineCoreBundle:Administration:group_list.html.twig',
            array('groups' => $groups)
        );
    }

    /**
     * Displays the users of a group.
     *
     * @param integer $groupId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function groupUserListAction($groupId)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $group = $em->getRepository('ClarolineCoreBundle:Group')->find($groupId);

        return $this->render(
            'ClarolineCoreBundle:Administration:group_user_list.html.twig',
            array('group' => $group)
        );
    }

    /**
     * Displays the user list with a control allowing to add them to a group.
     *
     * @param integer $groupId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function userListAddableToGroupAction($groupId)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $group = $em->getRepository('ClarolineCoreBundle:Group')->find($groupId);
        $users = $em->getRepository('ClarolineCoreBundle:User')->findAll();

        return $this->render(
            'ClarolineCoreBundle:Administration:user_list_addable_to_group.html.twig',
            array('group' => $group, 'users' => $users)
        );
    }

    /**
     * Adds an user to a group and redirects to the group list.
     *
     * @param integer $groupId
     * @param integer $userId
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function addUserToGroupAction($groupId, $userId)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $user = $em->getRepository('ClarolineCoreBundle:User')->find($userId);
        $group = $em->getRepository('ClarolineCoreBundle:Group')->find($groupId);
        $group->addUser($user);
        $em->persist($group);
        $em->flush();

        return $this->redirect($this->generateUrl('claro_admin_group_list'));
    }

    /**
     * Deletes an user from a group and redirects to the group list.
     *
     * @param integer $groupId
     * @param integer $userId
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteUserFromGroupAction($groupId, $userId)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $user = $em->getRepository('ClarolineCoreBundle:User')->find($userId);
        $group = $em->getRepository('ClarolineCoreBundle:Group')->find($groupId);
        $group->removeUser($user);
        $em->persist($group);
        $em->flush();

        return $this->redirect($this->generateUrl('claro_admin_group_list'));
    }

    /**
     * Deletes a group and redirects to the group list.
     *
     * @param integer $groupId
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteGroupAction($groupId)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $group = $em->getRepository('ClarolineCoreBundle:Group')->find($groupId);
        $em->remove($group);
        $em->flush();

        return $this->redirect($this->generateUrl('claro_admin_group_list'));
    }

    /**
     * Displays an edition form for a group.
     *
     * @param integer $groupId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function groupSettingsFormAction($groupId)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $group = $em->getRepository('ClarolineCoreBundle:Group')->find($groupId);
        $form = $this->createForm(new GroupSettingsType(), $group);

        return $this->render(
            'ClarolineCoreBundle:Administration:group_settings_form.html.twig',
            array('group' => $group, 'form_settings' => $form->createView())
        );
    }

    /**
     * Updates the settings of a group and redirects to the group list.
     *
     * @param integer $groupId
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function updateGroupSettingsAction($groupId)
    {
        $request = $this->get('request');
        $em = $this->getDoctrine()->getEntityManager();
        $group = $em->getRepository('ClarolineCoreBundle:Group')->find($groupId);
        $form = $this->createForm(new GroupSettingsType(), $group);
        $form->bindRequest($request);

        if ($form->isValid()) {
            $group = $form->getData();
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($group);
            $em->flush();

            return $this->redirect($this->generateUrl('claro_admin_group_list'));
        }

        $url = $this->generateUrl('claro_admin_group_list');

        return $this->render(
            'ClarolineCoreBundle:Administration:group_settings_form.html.twig',
            array('group' => $group, 'form_settings' => $form->createView())
        );
    }

    /**
     * Displays the platform settings.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function platformSettingsFormAction()
    {
        $platformConfig = $this->get('claroline.config.platform_config_handler')
            ->getPlatformConfig();
        $form = $this->createForm(new ClarolineSettingsType(), $platformConfig);

        return $this->render(
            'ClarolineCoreBundle:Administration:platform_settings_form.html.twig',
            array('form_settings' => $form->createView())
        );
    }

    /**
     * Updates the platform settings and redirects to the settings form.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function updatePlatformSettingsAction()
    {
        $request = $this->get('request');
        $configHandler = $this->get('claroline.config.platform_config_handler');
        $form = $this->get('form.factory')->create(new ClarolineSettingsType());
        $form->bindRequest($request);

        if ($form->isValid()) {
            $configHandler->setParameter('allow_self_registration', $form['selfRegistration']->getData());
            $configHandler->setParameter('locale_language', $form['localLanguage']->getData());
            $this->get('session')->setLocale($form['localLanguage']->getData());

            return $this->redirect($this->generateUrl('claro_admin_platform_settings_form'));

        }

        return $this->render(
            'ClarolineCoreBundle:Administration:platform_settings_form.html.twig',
            array('form_settings' => $form->createView())
        );
    }
}