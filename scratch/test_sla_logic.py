#!/usr/bin/env python3
"""
Simple script to test SLA escalation logic.

Usage:
    python scratch/test_sla_logic.py

This script demonstrates:
1. SLA due time calculation based on priority
2. SLA violation detection
3. How the decision engine handles SLA violations
"""

from datetime import datetime, timezone, timedelta
from app.db.models.enums import TicketPriority
from app.decision_engine.scorer import check_sla_violation
from app.services.ticket_service import TicketService


def main():
    print("=" * 60)
    print("SLA ESCALATION LOGIC TEST")
    print("=" * 60)

    # Test 1: SLA Calculation
    print("\n[TEST 1] SLA Due Time Calculation")
    print("-" * 60)
    now = datetime.now(timezone.utc)
    
    priorities = [
        (TicketPriority.CRITICAL, 2),
        (TicketPriority.HIGH, 4),
        (TicketPriority.MEDIUM, 8),
        (TicketPriority.LOW, 24),
    ]
    
    for priority, expected_hours in priorities:
        sla_due = TicketService.calculate_sla_due_time(priority, now)
        hours_diff = (sla_due - now).total_seconds() / 3600
        print(f"  {priority.value:10s}: SLA due in {hours_diff:5.1f} hours (expected: {expected_hours}h)")
        assert abs(hours_diff - expected_hours) < 0.1, f"SLA calculation mismatch for {priority}"

    # Test 2: SLA Violation Detection - No SLA
    print("\n[TEST 2] SLA Violation Detection - No SLA Set")
    print("-" * 60)
    is_violated, reason = check_sla_violation(None)
    print(f"  No SLA set -> violated={is_violated}, reason='{reason}'")
    assert is_violated is False, "Should not be violated when no SLA is set"

    # Test 3: SLA Violation Detection - Future SLA
    print("\n[TEST 3] SLA Violation Detection - Future SLA")
    print("-" * 60)
    future_sla = now + timedelta(hours=4)
    is_violated, reason = check_sla_violation(future_sla, current_time=now)
    print(f"  SLA in 4 hours -> violated={is_violated}, reason='{reason}'")
    assert is_violated is False, "Should not be violated when SLA is in the future"

    # Test 4: SLA Violation Detection - Exceeded SLA
    print("\n[TEST 4] SLA Violation Detection - Exceeded SLA")
    print("-" * 60)
    past_sla = now - timedelta(hours=2, minutes=30)
    is_violated, reason = check_sla_violation(past_sla, current_time=now)
    print(f"  SLA exceeded by 2.5 hours -> violated={is_violated}")
    print(f"    Reason: {reason}")
    assert is_violated is True, "Should be violated when SLA is exceeded"

    # Test 5: SLA Violation Detection - Just Exceeded
    print("\n[TEST 5] SLA Violation Detection - Just Exceeded")
    print("-" * 60)
    just_past_sla = now - timedelta(seconds=30)
    is_violated, reason = check_sla_violation(just_past_sla, current_time=now)
    print(f"  SLA exceeded by 30 seconds -> violated={is_violated}")
    print(f"    Reason: {reason}")
    assert is_violated is True, "Should detect even small violations"

    # Test 6: Simulation - Ticket lifecycle
    print("\n[TEST 6] Ticket Lifecycle Simulation")
    print("-" * 60)
    
    creation_time = datetime(2024, 1, 15, 10, 0, 0, tzinfo=timezone.utc)
    ticket_priority = TicketPriority.HIGH  # 4-hour SLA
    
    sla_due = TicketService.calculate_sla_due_time(ticket_priority, creation_time)
    print(f"  Created at:       {creation_time.isoformat()}")
    print(f"  Priority:         {ticket_priority.value}")
    print(f"  SLA Due:          {sla_due.isoformat()}")
    
    # Simulate different time points
    test_times = [
        (creation_time + timedelta(hours=1), "1 hour after creation"),
        (creation_time + timedelta(hours=3.5), "3.5 hours after creation"),
        (creation_time + timedelta(hours=4), "Exactly at SLA due time"),
        (creation_time + timedelta(hours=4, minutes=30), "4.5 hours after creation"),
    ]
    
    for check_time, description in test_times:
        is_violated, reason = check_sla_violation(sla_due, current_time=check_time)
        status = "VIOLATED" if is_violated else "OK"
        print(f"    {description:30s} -> {status:10s} ({reason or 'SLA valid'})")

    print("\n" + "=" * 60)
    print("ALL TESTS PASSED!")
    print("=" * 60)
    print("\nSummary:")
    print("  ✓ SLA due times calculated correctly based on priority")
    print("  ✓ SLA violation detection works for all scenarios")
    print("  ✓ Edge cases (no SLA, just exceeded) handled correctly")
    print("\nHow the Decision Engine uses this:")
    print("  1. When analyzing a ticket, check_sla_violation() is called")
    print("  2. If SLA is violated:")
    print("     - Risk level boosted to CRITICAL")
    print("     - Priority upgraded to CRITICAL")
    print("     - Status changed to ESCALATED")
    print("     - is_sla_violated flag set to True")
    print("  3. Periodic task (every 15 min) catches any missed violations")


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
