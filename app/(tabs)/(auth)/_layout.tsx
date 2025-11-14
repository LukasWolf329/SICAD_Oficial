import { ThemeProvider, DarkTheme, DefaultTheme } from '@react-navigation/native';
import { useColorScheme } from '@/hooks/useColorScheme';
import { Stack, Slot, useRouter } from 'expo-router';
import { View } from 'react-native';
import { useEffect, useState } from "react";
import AsyncStorage from "@react-native-async-storage/async-storage";


export default function AuthLayout() {
  const colorScheme = useColorScheme();

  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <View className="flex-1 bg-white dark:bg-[#121212] justify-center">
        <Stack screenOptions={{ headerShown: false }} />
      </View>
    </ThemeProvider>
  );
}


