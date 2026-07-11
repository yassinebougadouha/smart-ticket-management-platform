"""
Test SLA escalation logic for urgent tickets.

Tests that:
1. SLA due time is calculated correctly based on priority
2. SLA violations trigger ESCALATE_HUMAN outcome
3. Ticket priority is upgraded to CRITICAL on SLA violation
4. Ticket status is set to ESCALATED
"""

import pytest
from datetime import datetime, timezone, timedelta
from uuid import uuid4

from app.db.models.ticket import Ticket
from app.db.models.enums import TicketPriority, TicketStatus
from app.decision_engine.scorer import check_sla_violation
from app.services.ticket_service import TicketService


class TestSLACalculation:
    """Test SLA due time calculation based on priority."""

    def test_sla_time_critical(self):
        """CRITICAL tickets should have 2-hour SLA."""
        now = datetime.now(timezone.utc)
        sla_due = TicketService.calculate_sla_due_time(TicketPriority.CRITICAL, now)
        expected = now + timedelta(hours=2)
        
        # Allow 1 minute tolerance for test execution time
        assert abs((sla_due - expected).total_seconds()) < 60

    def test_sla_time_high(self):
        """HIGH priority tickets should have 4-hour SLA."""
        now = datetime.now(timezone.utc)
        sla_due = TicketService.calculate_sla_due_time(TicketPriority.HIGH, now)
        expected = now + timedelta(hours=4)
        
        assert abs((sla_due - expected).total_seconds()) < 60

    def test_sla_time_medium(self):
        """MEDIUM priority tickets should have 8-hour SLA."""
        now = datetime.now(timezone.utc)
        sla_due = TicketService.calculate_sla_due_time(TicketPriority.MEDIUM, now)
        expected = now + timedelta(hours=8)
        
        assert abs((sla_due - expected).total_seconds()) < 60

    def test_sla_time_low(self):
        """LOW priority tickets should have 24-hour SLA."""
        now = datetime.now(timezone.utc)
        sla_due = TicketService.calculate_sla_due_time(TicketPriority.LOW, now)
        expected = now + timedelta(hours=24)
        
        assert abs((sla_due - expected).total_seconds()) < 60


class TestSLAViolationDetection:
    """Test SLA violation checking."""

    def test_no_sla_set(self):
        """Should return (False, '') when no SLA is set."""
        is_violated, reason = check_sla_violation(None)
        assert is_violated is False
        assert reason == ""

    def test_sla_not_yet_exceeded(self):
        """Should return (False, '') when SLA has not been exceeded."""
        future_time = datetime.now(timezone.utc) + timedelta(hours=1)
        is_violated, reason = check_sla_violation(future_time)
        assert is_violated is False
        assert reason == ""

    def test_sla_exceeded(self):
        """Should return (True, reason) when SLA has been exceeded."""
        past_time = datetime.now(timezone.utc) - timedelta(hours=1)
        is_violated, reason = check_sla_violation(past_time)
        assert is_violated is True
        assert "SLA violated" in reason
        assert "hours" in reason

    def test_sla_barely_exceeded(self):
        """Should detect even small SLA violations."""
        past_time = datetime.now(timezone.utc) - timedelta(seconds=1)
        is_violated, reason = check_sla_violation(past_time)
        assert is_violated is True

    def test_sla_with_custom_time(self):
        """Should use provided current_time for comparison."""
        now = datetime(2024, 1, 1, 12, 0, 0, tzinfo=timezone.utc)
        sla_due = datetime(2024, 1, 1, 10, 0, 0, tzinfo=timezone.utc)  # 2 hours ago
        
        is_violated, reason = check_sla_violation(sla_due, current_time=now)
        assert is_violated is True
        assert "2.0 hours" in reason


class TestSLAEscalationFlow:
    """Test the full SLA escalation flow."""

    @pytest.mark.asyncio
    async def test_sla_exceeded_triggers_escalation(self, async_db):
        """
        Test that a ticket with exceeded SLA is escalated when analyzed.

        Flow:
        1. Create ticket with MEDIUM priority (8-hour SLA)
        2. Set sla_due_at to 1 hour ago
        3. Run decision engine
        4. Should result in ESCALATE_HUMAN outcome
        5. Priority should be CRITICAL
        6. Status should be ESCALATED
        """
        from app.decision_engine.decision_engine import analyze_ticket
        from app.decision_engine.enums import DecisionOutcome

        # Create ticket
        ticket = Ticket(
            subject="Test urgent ticket with SLA violation",
            description="This ticket is old and should be escalated due to SLA violation",
            priority=TicketPriority.MEDIUM,
            status=TicketStatus.OPEN,
            creator_id=uuid4(),
        )

        # Set SLA to 1 hour ago
        ticket.sla_due_at = datetime.now(timezone.utc) - timedelta(hours=1)

        async_db.add(ticket)
        await async_db.flush()

        # Run decision engine
        result = await analyze_ticket(
            db=async_db,
            ticket=ticket,
            auto_assign=True,
            auto_update_priority=True,
        )

        # Verify escalation was triggered
        assert result.decision_outcome == DecisionOutcome.ESCALATE_HUMAN
        assert ticket.priority == TicketPriority.CRITICAL
        assert ticket.status == TicketStatus.ESCALATED
        assert ticket.is_sla_violated is True
        assert ticket.sla_violated_at is not None

    @pytest.mark.asyncio
    async def test_sla_not_exceeded_no_escalation(self, async_db):
        """
        Test that a ticket with valid SLA is not escalated for SLA reasons.

        Flow:
        1. Create ticket with LOW priority (24-hour SLA)
        2. SLA due time is 23 hours from now (valid)
        3. Run decision engine with low-risk content
        4. Should NOT result in ESCALATE_HUMAN (unless other reasons)
        """
        from app.decision_engine.decision_engine import analyze_ticket
        from app.decision_engine.enums import DecisionOutcome

        # Create ticket
        ticket = Ticket(
            subject="Normal ticket with valid SLA",
            description="This is a normal request and SLA is not violated",
            priority=TicketPriority.LOW,
            status=TicketStatus.OPEN,
            creator_id=uuid4(),
        )

        # Set SLA to 23 hours from now (not violated)
        ticket.sla_due_at = datetime.now(timezone.utc) + timedelta(hours=23)

        async_db.add(ticket)
        await async_db.flush()

        # Run decision engine
        result = await analyze_ticket(
            db=async_db,
            ticket=ticket,
            auto_assign=True,
            auto_update_priority=True,
        )

        # Verify SLA is not marked as violated
        assert ticket.is_sla_violated is False
        # Priority should not be forced to CRITICAL by SLA (could be LOW due to content)
        # Outcome depends on content analysis, not SLA
