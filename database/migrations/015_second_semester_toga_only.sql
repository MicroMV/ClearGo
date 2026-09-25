-- Toga return applies only to graduating Year 4 students during the second semester.
DELETE item
FROM facilities_obligations item
INNER JOIN academic_periods period ON period.id=item.academic_period_id
WHERE item.item_type='TOGA' AND period.semester<>'2ND';
