import { useCategories } from '@/hooks/useCategories';
import { ButtonCategoryNav } from '@components/Navigation/ButtonCategoryNav';
import { useLocation } from 'preact-iso';
import { capitalizeFirstLetter } from '@/utils';

export function CategoriesMenuNav() {

  const categories = useCategories();
  const { url } = useLocation();

  return (
    <div id="mainnav" className="border-bottom border-2 border-dark w-100 p-2 btn-scroll-container text-center">
      {categories.map((category) => (
        <ButtonCategoryNav
          key={category}
          name={capitalizeFirstLetter(category)}
          category={category}
          isActive={url === `/category/${category}`}
        />
      ))}
    </div>
  );
}