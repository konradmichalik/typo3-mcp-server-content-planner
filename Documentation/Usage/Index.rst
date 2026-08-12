.. include:: /Includes.rst.txt

.. _usage:

=====
Usage
=====

Once both ``hn/typo3-mcp-server`` and ``xima/xima-typo3-content-planner`` are installed
and configured, this extension registers four additional MCP tools automatically — no
further setup is required.

ListContentPlannerStatuses
===========================

Lists all Content Planner statuses configured on this installation.

GetContentPlannerInfo
======================

Reads the status, assignee and comments (including threaded replies) of a single
record. Requires ``table`` and ``uid``.

SetContentPlannerStatus
========================

Sets the status (and optionally the assignee) of a record. Requires ``table``, ``uid``
and ``status``. Writes immediately to the live workspace.

AddContentPlannerComment
==========================

Leaves a comment, optionally with a to-do checklist, on a record. Requires ``table``,
``uid`` and ``comment``. Pass ``parentCommentUid`` (the UID of an existing comment from
``GetContentPlannerInfo``) to reply within that comment's thread instead of creating a
new top-level comment. Writes immediately to the live workspace.
