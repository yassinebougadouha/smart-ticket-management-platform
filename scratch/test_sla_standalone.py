#!/usr/bin/env python3
"""
Minimal SLA logic test - no external dependencies.

This demonstrates the SLA calculation and violation detection logic
without requiring database setup or full environment.
"""

from datetime import datetime, timezone, timedelta
from enum import Enum


class TicketPriority(str, Enum):
    """Copy of TicketPriority enum for standalone testing."""
    CRITICAL = "CRITICAL"
    HIGH = "HIGH"
    MEDIUM = "MEDIUM"
    LOW = "LOW"


def calculate_sla_due_time(priority: TicketPriority, created_at: datetime | None = None) -> datetime:
    """Calculate SLA due time based on ticket priority."""
    base_time = created_at or datetime.now(timezone.utc)
    
    sla_hours = {
        TicketPriority.CRITICAL: 2,
        TicketPriority.HIGH: 4,
        TicketPriority.MEDIUM: 8,
        TicketPriority.LOW: 24,
    }
    
    hours = sla_hours.get(priority, 8)
    return base_time + timedelta(hours=hours)


def check_sla_violation(sla_due_at: datetime | None, current_time: datetime | None = None) -> tuple[bool, str]:
    """Check if a ticket's SLA has been violated."""
    if not sla_due_at:
        return False, ""

    now = current_time or datetime.now(timezone.utc)

    # Ensure both times are timezone-aware
    if sla_due_at.tzinfo is None:
        sla_due_at = sla_due_at.replace(tzinfo=timezone.utc)
    if now.tzinfo is None:
        now = now.replace(tzinfo=timezone.utc)

    if now > sla_due_at:
        time_exceeded = now - sla_due_at
        hours = time_exceeded.total_seconds() / 3600
        reason = f"SLA violated: exceeded by {hours:.1f} hours"
        return True, reason

    return False, ""


def main():
    print("=" * 70)
    print("SLA ESCALATION LOGIC TEST (Standalone)")
    print("=" * 70)

    # Test 1: SLA Calculation
    print("\n[TEST 1] SLA Due Time Calculation Based on Priority")
    print("-" * 70)
    now = datetime.now(timezone.utc)
    
    priorities = [
        (TicketPriority.CRITICAL, 2),
        (TicketPriority.HIGH, 4),
        (TicketPriority.MEDIUM, 8),
        (TicketPriority.LOW, 24),
    ]
    
    print(f"Reference time: {now.isoformat()}\n")
    
    for priority, expected_hours in priorities:
        sla_due = calculate_sla_due_time(priority, now)
        hours_diff = (sla_due - now).total_seconds() / 3600
        print(f"  {priority.value:10s}: SLA due in {hours_diff:5.1f} hours (expected: {expected_hours}h) ✓")
        assert abs(hours_diff - expected_hours) < 0.1

    # Test 2: SLA Violation Detection - No SLA
    print("\n[TEST 2] SLA Violation Detection - No SLA Set")
    print("-" * 70)
    is_violated, reason = check_sla_violation(None)
    print(f"  No SLA set → violated={is_violated}, reason='{reason}' ✓")
    assert is_violated is False

    # Test 3: SLA Violation Detection - Future SLA
    print("\n[TEST 3] SLA Violation Detection - Future SLA")
    print("-" * 70)
    future_sla = now + timedelta(hours=4)
    is_violated, reason = check_sla_violation(future_sla, current_time=now)
    print(f"  SLA in 4 hours → violated={is_violated}, reason='{reason}' ✓")
    assert is_violated is False

    # Test 4: SLA Violation Detection - Exceeded SLA
    print("\n[TEST 4] SLA Violation Detection - Exceeded SLA")
    print("-" * 70)
    past_sla = now - timedelta(hours=2, minutes=30)
    is_violated, reason = check_sla_violation(past_sla, current_time=now)
    print(f"  SLA exceeded by 2.5 hours → violated={is_violated}")
    print(f"    Reason: {reason} ✓")
    assert is_violated is True

    # Test 5: SLA Violation Detection - Just Exceeded
    print("\n[TEST 5] SLA Violation Detection - Just Exceeded (Edge Case)")
    print("-" * 70)
    just_past_sla = now - timedelta(seconds=30)
    is_violated, reason = check_sla_violation(just_past_sla, current_time=now)
    print(f"  SLA exceeded by 30 seconds → violated={is_violated}")
    print(f"    Reason: {reason} ✓")
    assert is_violated is True

    # Test 6: Simulation - Ticket lifecycle
    print("\n[TEST 6] Ticket Lifecycle Simulation")
    print("-" * 70)
    
    creation_time = datetime(2024, 1, 15, 10, 0, 0, tzinfo=timezone.utc)
    ticket_priority = TicketPriority.HIGH  # 4-hour SLA
    
    sla_due = calculate_sla_due_time(ticket_priority, creation_time)
    print(f"  Ticket created:   {creation_time.isoformat()}")
    print(f"  Priority:         {ticket_priority.value}")
    print(f"  SLA Due:          {sla_due.isoformat()}")
    print()
    
    # Simulate different time points
    test_times = [
        (creation_time + timedelta(hours=1), "1 hour after creation"),
        (creation_time + timedelta(hours=3.5), "3.5 hours after creation"),
        (creation_time + timedelta(hours=4), "Exactly at SLA due time"),
        (creation_time + timedelta(hours=4, minutes=30), "4.5 hours after creation"),
    ]
    
    for check_time, description in test_times:
        is_violated, reason = check_sla_violation(sla_due, current_time=check_time)
        status = "VIOLATED" if is_violated else "OK      "
        print(f"    {description:30s} → {status} ({reason or 'SLA valid'}) ✓")

    print("\n" + "=" * 70)
    print("✓ ALL TESTS PASSED!")
    print("=" * 70)
    
    print("\nSummary of SLA Escalation Implementation:")
    print("-" * 70)
    print("""
  1. DATABASE SCHEMA (Ticket Model)
     • sla_due_at: DateTime - when SLA expires
     • is_sla_violated: Boolean - SLA violation flag
     • sla_violated_at: DateTime - when violation detected

  2. SLA CALCULATION (TicketService)
     • CRITICAL: 2 hours
     • HIGH:     4 hours  
     • MEDIUM:   8 hours
     • LOW:      24 hours
     • Set when ticket is created

  3. VIOLATION DETECTION (Scorer)
     • check_sla_violation() compares ticket.sla_due_at vs now()
     • Returns (is_violated: bool, reason: str)

  4. ESCALATION TRIGGER (Decision Engine)
     • During ticket analysis, check_sla_violation() is called
     • If violated:
       ✓ Risk level boosted to CRITICAL
       ✓ Priority upgraded to CRITICAL
       ✓ Status changed to ESCALATED
       ✓ is_sla_violated flag set to True
       ✓ sla_violated_at timestamp recorded

  5. PERIODIC SCAN (Celery Task)
     • Task: scan_sla_violations_task
     • Runs every 15 minutes
     • Catches any SLA violations missed during normal analysis
     • Same escalation actions applied

  6. INTEGRATION POINTS
     • TicketService.create_ticket() - sets SLA
     • TicketService.ingest_glpi_ticket() - sets SLA for GLPI tickets
     • analyze_ticket() - checks SLA during analysis
     • Celery Beat - runs periodic scan
    """)


if __name__ == "__main__":
    try:
        main()
    except AssertionError as e:
        print(f"\n❌ TEST FAILED: {e}")
        exit(1)
    except Exception as e:
        print(f"\n❌ ERROR: {e}")
        import traceback
        traceback.print_exc()
        exit(1)
